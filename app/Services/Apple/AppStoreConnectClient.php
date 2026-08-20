<?php

namespace App\Services\Apple;

use DateTimeImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Ecdsa\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use RuntimeException;

class AppStoreConnectClient
{
    private const BASE_URL = 'https://api.appstoreconnect.apple.com';

    private ?string $cachedToken = null;

    private ?DateTimeImmutable $tokenExpiresAt = null;

    public function isConfigured(): bool
    {
        return $this->privateKey() !== ''
            && (string) config('apple_app_store.key_id') !== ''
            && (string) config('apple_app_store.issuer_id') !== '';
    }

    public function getAppIdByBundleId(string $bundleId): ?string
    {
        $response = $this->request()
            ->get('/v1/apps', [
                'filter[bundleId]' => $bundleId,
                'limit' => 1,
            ]);

        $this->throwIfFailed($response, 'Unable to resolve App Store app ID.');

        return $response->json('data.0.id');
    }

    /**
     * @return array<string, string> productId => inAppPurchaseId
     */
    public function listInAppPurchaseIdsForApp(string $appId): array
    {
        $productIds = [];
        $url = '/v1/apps/'.$appId.'/inAppPurchasesV2?limit=200';

        while ($url !== '') {
            $response = $this->request()->get($url);
            $this->throwIfFailed($response, 'Unable to list in-app purchases.');

            foreach ($response->json('data', []) as $item) {
                $productId = (string) ($item['attributes']['productId'] ?? '');
                $iapId = (string) ($item['id'] ?? '');

                if ($productId !== '' && $iapId !== '') {
                    $productIds[$productId] = $iapId;
                }
            }

            $url = $this->nextUrl($response->json('links.next'));
        }

        return $productIds;
    }

    /**
     * @return array{id: string, product_id: string, state: string|null}
     */
    public function createConsumableInAppPurchase(string $appId, string $productId, string $referenceName, string $reviewNote): array
    {
        $response = $this->request()->post('/v2/inAppPurchases', [
            'data' => [
                'type' => 'inAppPurchases',
                'attributes' => [
                    'name' => $referenceName,
                    'productId' => $productId,
                    'inAppPurchaseType' => 'CONSUMABLE',
                    'reviewNote' => $reviewNote,
                ],
                'relationships' => [
                    'app' => [
                        'data' => [
                            'type' => 'apps',
                            'id' => $appId,
                        ],
                    ],
                ],
            ],
        ]);

        $this->throwIfFailed($response, 'Unable to create in-app purchase.');

        return [
            'id' => (string) $response->json('data.id'),
            'product_id' => (string) $response->json('data.attributes.productId'),
            'state' => $response->json('data.attributes.state'),
        ];
    }

    /**
     * @return array{id: string, state: string|null}
     */
    public function createInAppPurchaseVersion(string $inAppPurchaseId): array
    {
        $response = $this->request()->post('/v1/inAppPurchaseVersions', [
            'data' => [
                'type' => 'inAppPurchaseVersions',
                'relationships' => [
                    'inAppPurchase' => [
                        'data' => [
                            'type' => 'inAppPurchases',
                            'id' => $inAppPurchaseId,
                        ],
                    ],
                ],
            ],
        ]);

        $this->throwIfFailed($response, 'Unable to create in-app purchase version.');

        return [
            'id' => (string) $response->json('data.id'),
            'state' => $response->json('data.attributes.state'),
        ];
    }

    public function createInAppPurchaseLocalization(string $versionId, string $locale, string $name, string $description): void
    {
        $response = $this->request()->post('/v2/inAppPurchaseLocalizations', [
            'data' => [
                'type' => 'inAppPurchaseLocalizations',
                'attributes' => [
                    'locale' => $locale,
                    'name' => $name,
                    'description' => $description,
                ],
                'relationships' => [
                    'version' => [
                        'data' => [
                            'type' => 'inAppPurchaseVersions',
                            'id' => $versionId,
                        ],
                    ],
                ],
            ],
        ]);

        $this->throwIfFailed($response, 'Unable to create in-app purchase localization.');
    }

    /**
     * @return array<int, array{id: string, customer_price: float, price_tier: string|null}>
     */
    public function listPricePointsForInAppPurchase(string $inAppPurchaseId, string $territory): array
    {
        $points = [];
        $url = '/v2/inAppPurchases/'.$inAppPurchaseId.'/pricePoints?filter[territory]='.$territory.'&limit=200';

        while ($url !== '') {
            $response = $this->request()->get($url);
            $this->throwIfFailed($response, 'Unable to list in-app purchase price points.');

            foreach ($response->json('data', []) as $item) {
                $points[] = [
                    'id' => (string) ($item['id'] ?? ''),
                    'customer_price' => (float) ($item['attributes']['customerPrice'] ?? 0),
                    'price_tier' => isset($item['attributes']['priceTier'])
                        ? (string) $item['attributes']['priceTier']
                        : null,
                ];
            }

            $url = $this->nextUrl($response->json('links.next'));
        }

        return $points;
    }

    public function setInAppPurchasePrice(string $inAppPurchaseId, string $pricePointId): void
    {
        $temporaryPriceId = '${price-'.md5($inAppPurchaseId.$pricePointId).'}';

        $response = $this->request()->patch('/v2/inAppPurchases/'.$inAppPurchaseId, [
            'data' => [
                'type' => 'inAppPurchases',
                'id' => $inAppPurchaseId,
                'attributes' => (object) [],
                'relationships' => [
                    'prices' => [
                        'data' => [
                            [
                                'type' => 'inAppPurchasePrices',
                                'id' => $temporaryPriceId,
                            ],
                        ],
                    ],
                ],
            ],
            'included' => [
                [
                    'type' => 'inAppPurchasePrices',
                    'id' => $temporaryPriceId,
                    'attributes' => [
                        'startDate' => null,
                    ],
                    'relationships' => [
                        'inAppPurchaseV2' => [
                            'data' => [
                                'type' => 'inAppPurchases',
                                'id' => $inAppPurchaseId,
                            ],
                        ],
                        'inAppPurchasePricePoint' => [
                            'data' => [
                                'type' => 'inAppPurchasePricePoints',
                                'id' => $pricePointId,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->throwIfFailed($response, 'Unable to set in-app purchase price.');
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->acceptJson()
            ->withToken($this->token())
            ->timeout(60)
            ->retry(2, 500, throw: false);
    }

    private function token(): string
    {
        if ($this->cachedToken !== null && $this->tokenExpiresAt !== null && $this->tokenExpiresAt > new DateTimeImmutable('+1 minute')) {
            return $this->cachedToken;
        }

        $privateKey = $this->privateKey();

        if ($privateKey === '') {
            throw new RuntimeException('App Store Connect private key is not configured.');
        }

        $configuration = Configuration::forAsymmetricSigner(
            new Sha256,
            InMemory::plainText($privateKey),
            InMemory::plainText($privateKey),
        );

        $now = new DateTimeImmutable;
        $expiresAt = $now->modify('+19 minutes');

        $token = $configuration->builder()
            ->issuedBy((string) config('apple_app_store.issuer_id'))
            ->issuedAt($now)
            ->expiresAt($expiresAt)
            ->withHeader('kid', (string) config('apple_app_store.key_id'))
            ->permittedFor('appstoreconnect-v1')
            ->getToken($configuration->signer(), $configuration->signingKey())
            ->toString();

        $this->cachedToken = $token;
        $this->tokenExpiresAt = $expiresAt;

        return $token;
    }

    private function privateKey(): string
    {
        $inlineKey = trim((string) config('apple_app_store.private_key'));

        if ($inlineKey !== '') {
            return str_contains($inlineKey, '\\n')
                ? str_replace('\\n', PHP_EOL, $inlineKey)
                : $inlineKey;
        }

        $path = (string) config('apple_app_store.private_key_path');

        if ($path !== '' && is_readable($path)) {
            return trim((string) file_get_contents($path));
        }

        return '';
    }

    private function nextUrl(?string $url): string
    {
        if ($url === null || $url === '') {
            return '';
        }

        return str_starts_with($url, self::BASE_URL)
            ? substr($url, strlen(self::BASE_URL))
            : $url;
    }

    private function throwIfFailed(Response $response, string $message): void
    {
        if ($response->successful()) {
            return;
        }

        $errors = collect($response->json('errors', []))
            ->map(fn (array $error): string => trim(($error['title'] ?? 'Error').': '.($error['detail'] ?? '')))
            ->filter()
            ->implode(' | ');

        throw new RuntimeException(trim($message.' '.$errors));
    }
}
