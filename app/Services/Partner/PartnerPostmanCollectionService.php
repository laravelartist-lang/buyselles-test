<?php

namespace App\Services\Partner;

use Illuminate\Support\Facades\File;

class PartnerPostmanCollectionService
{
    private const TEMPLATE_PATH = 'postman/Buyselles_Partner_API.postman_collection.json';

    public function __construct(
        private readonly PartnerProductCatalogQuery $catalogQuery,
    ) {}

    public function generate(): string
    {
        $templatePath = resource_path(self::TEMPLATE_PATH);

        if (! File::exists($templatePath)) {
            throw new \RuntimeException('Partner API Postman template not found at: '.$templatePath);
        }

        /** @var array<string, mixed> $collection */
        $collection = json_decode(File::get($templatePath), true, 512, JSON_THROW_ON_ERROR);

        $items = $collection['item'] ?? [];
        $this->normalizeCollectionUrls($items);
        $collection['item'] = $items;

        $collection['variable'] = $this->buildVariables();
        $collection['item'][] = $this->buildExamplesFolder();

        return json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildVariables(): array
    {
        $samples = $this->catalogQuery->sampleProductIds();
        $baseUrl = rtrim((string) config('app.url'), '/');
        $defaultProductId = $samples['in_house_local'] ?? $samples['supplier_denomination'] ?? $samples['supplier_mapped'];

        return [
            [
                'key' => 'base_url',
                'value' => $baseUrl,
                'type' => 'string',
                'description' => 'Your Buyselles server URL',
            ],
            [
                'key' => 'api_key',
                'value' => '',
                'type' => 'string',
                'description' => 'Partner API key (X-API-KEY header)',
            ],
            [
                'key' => 'api_secret',
                'value' => '',
                'type' => 'string',
                'description' => 'Partner API secret (X-API-SECRET header)',
            ],
            [
                'key' => 'product_id',
                'value' => $defaultProductId !== null ? (string) $defaultProductId : '',
                'type' => 'string',
                'description' => 'Product ID from GET /products — used in detail, quote, and order requests',
            ],
            [
                'key' => 'quantity',
                'value' => '1',
                'type' => 'string',
                'description' => 'Order/quote quantity (1–100)',
            ],
            [
                'key' => 'order_reference',
                'value' => 'postman-order-001',
                'type' => 'string',
                'description' => 'Your internal order reference (optional)',
            ],
            [
                'key' => 'supplier_denomination_id',
                'value' => '',
                'type' => 'string',
                'description' => 'Optional for fixed products — required only when pricing.type is denominations. pricing.denominations[].id from GET /products/{id}',
            ],
            [
                'key' => 'custom_amount',
                'value' => '50',
                'type' => 'string',
                'description' => 'Face value for variable denominations (within min/max from product detail)',
            ],
            [
                'key' => 'expected_total',
                'value' => '',
                'type' => 'string',
                'description' => 'Required when pricing.type is denominations or fulfillment_type is direct_topup — copy data.total from quote',
            ],
            [
                'key' => 'direct_topup_account_id',
                'value' => '',
                'type' => 'string',
                'description' => 'Required for direct top-up products — player/account ID to credit',
            ],
            [
                'key' => 'order_id',
                'value' => '',
                'type' => 'string',
                'description' => 'Order ID for GET /orders/{id}',
            ],
            [
                'key' => 'product_search',
                'value' => 'steam',
                'type' => 'string',
                'description' => 'Search keyword for List Products — Search',
            ],
            [
                'key' => 'example_in_house_local_product_id',
                'value' => $samples['in_house_local'] !== null ? (string) $samples['in_house_local'] : '',
                'type' => 'string',
                'description' => 'Sample: in-house product fulfilled from local code pool',
            ],
            [
                'key' => 'example_supplier_mapped_product_id',
                'value' => $samples['supplier_mapped'] !== null ? (string) $samples['supplier_mapped'] : '',
                'type' => 'string',
                'description' => 'Sample: in-house product mapped to Bamboo/Golf supplier',
            ],
            [
                'key' => 'example_supplier_denomination_product_id',
                'value' => $samples['supplier_denomination'] !== null ? (string) $samples['supplier_denomination'] : '',
                'type' => 'string',
                'description' => 'Sample: supplier-mapped product with denominations',
            ],
            [
                'key' => 'example_vendor_product_id',
                'value' => $samples['vendor'] !== null ? (string) $samples['vendor'] : '',
                'type' => 'string',
                'description' => 'Sample: vendor product (requires include_vendor=1)',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildExamplesFolder(): array
    {
        return [
            'name' => '6. Examples by product source',
            'description' => 'Pre-filled requests using sample product IDs from your catalog. Check order_requirements on product detail — Bamboo/supplier products use simple quote/order unless pricing.type is denominations.',
            'item' => [
                $this->buildGetRequest(
                    'List Products — in-house only (default)',
                    '/api/v1/partner/products',
                    'Returns only in-house (admin) digital products.'
                ),
                $this->buildGetRequest(
                    'List Products — include vendor',
                    '/api/v1/partner/products?include_vendor=1',
                    'Includes partner-approved vendor products.'
                ),
                $this->buildGetRequest(
                    'List Products — supplier-mapped only',
                    '/api/v1/partner/products?fulfillment_type=supplier_codes',
                    'Only supplier-fulfilled products (Bamboo, Golf API, etc.).'
                ),
                $this->buildGetRequest(
                    'Get Product — in-house local',
                    '/api/v1/partner/products/{{example_in_house_local_product_id}}',
                    'Detail for an in-house product with local code pool fulfillment.'
                ),
                $this->buildGetRequest(
                    'Get Product — supplier mapped',
                    '/api/v1/partner/products/{{example_supplier_mapped_product_id}}',
                    'Detail for a supplier-mapped in-house product.'
                ),
                $this->buildGetRequest(
                    'Get Product — supplier denomination',
                    '/api/v1/partner/products/{{example_supplier_denomination_product_id}}',
                    'Detail when pricing.type is denominations — copy pricing.denominations[].id to supplier_denomination_id.'
                ),
                $this->buildPostOrderRequest(
                    'Create Order — in-house local product',
                    '{{example_in_house_local_product_id}}',
                    'Simple order — product_id + quantity only.',
                    'simple',
                ),
                $this->buildPostQuoteRequest(
                    'Quote Product — supplier-mapped (simple)',
                    '{{example_supplier_mapped_product_id}}',
                    'Simple Bamboo quote — quantity only. Optionally enable supplier_denomination_id when order_requirements lists it.',
                    'simple',
                ),
                $this->buildPostOrderRequest(
                    'Create Order — supplier-mapped product',
                    '{{example_supplier_mapped_product_id}}',
                    'Simple Bamboo order — product_id + quantity only. Optional supplier_denomination_id when listed in order_requirements.',
                    'simple',
                ),
                $this->buildPostQuoteRequest(
                    'Quote Product — denomination example',
                    '{{example_supplier_denomination_product_id}}',
                    'Use when pricing.type is denominations — set supplier_denomination_id.',
                    'denomination',
                ),
                $this->buildPostOrderRequest(
                    'Create Order — denomination example',
                    '{{example_supplier_denomination_product_id}}',
                    'Use when pricing.type is denominations — supplier_denomination_id + expected_total from quote.',
                    'denomination',
                ),
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $query
     * @return array<string, mixed>
     */
    private function buildUrlWithQuery(string $path, array $query): array
    {
        $activeQuery = array_values(array_filter(
            $query,
            fn (array $param): bool => ! ($param['disabled'] ?? false),
        ));

        $queryString = implode('&', array_map(
            fn (array $param): string => $param['key'].'='.$param['value'],
            $activeQuery,
        ));

        return [
            'raw' => '{{base_url}}/'.$path.($queryString !== '' ? '?'.$queryString : ''),
            'query' => $query,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function simpleQuoteQueryParams(): array
    {
        return [
            [
                'key' => 'quantity',
                'value' => '{{quantity}}',
                'description' => 'Required. 1–100',
            ],
            [
                'key' => 'supplier_denomination_id',
                'value' => '{{supplier_denomination_id}}',
                'description' => 'Optional on fixed products with synced denominations',
                'disabled' => true,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function denominationQuoteQueryParams(): array
    {
        return [
            [
                'key' => 'quantity',
                'value' => '{{quantity}}',
                'description' => 'Required. 1–100',
            ],
            [
                'key' => 'supplier_denomination_id',
                'value' => '{{supplier_denomination_id}}',
                'description' => 'Required when pricing.type is denominations',
            ],
            [
                'key' => 'custom_amount',
                'value' => '{{custom_amount}}',
                'description' => 'Variable denomination only',
                'disabled' => true,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function simpleOrderQueryParams(string $productIdVariable): array
    {
        return [
            [
                'key' => 'product_id',
                'value' => $productIdVariable,
                'description' => 'Required',
            ],
            [
                'key' => 'quantity',
                'value' => '{{quantity}}',
                'description' => 'Required. 1–100',
            ],
            [
                'key' => 'reference',
                'value' => '{{order_reference}}',
                'description' => 'Optional',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function denominationOrderQueryParams(string $productIdVariable): array
    {
        return [
            [
                'key' => 'product_id',
                'value' => $productIdVariable,
                'description' => 'Required',
            ],
            [
                'key' => 'quantity',
                'value' => '{{quantity}}',
                'description' => 'Required. 1–100',
            ],
            [
                'key' => 'reference',
                'value' => '{{order_reference}}',
                'description' => 'Optional',
            ],
            [
                'key' => 'supplier_denomination_id',
                'value' => '{{supplier_denomination_id}}',
                'description' => 'Required when pricing.type is denominations',
            ],
            [
                'key' => 'custom_amount',
                'value' => '{{custom_amount}}',
                'description' => 'Variable denomination only',
                'disabled' => true,
            ],
            [
                'key' => 'expected_total',
                'value' => '{{expected_total}}',
                'description' => 'From quote response',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function directTopupOrderQueryParams(string $productIdVariable): array
    {
        return [
            [
                'key' => 'product_id',
                'value' => $productIdVariable,
                'description' => 'Required',
            ],
            [
                'key' => 'quantity',
                'value' => '{{quantity}}',
                'description' => 'Required. Must be 1',
            ],
            [
                'key' => 'reference',
                'value' => '{{order_reference}}',
                'description' => 'Optional',
            ],
            [
                'key' => 'direct_topup_account_id',
                'value' => '{{direct_topup_account_id}}',
                'description' => 'Required for direct top-up',
            ],
            [
                'key' => 'expected_total',
                'value' => '{{expected_total}}',
                'description' => 'From quote response',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGetRequest(string $name, string $rawUrl, string $description): array
    {
        $path = ltrim(parse_url($rawUrl, PHP_URL_PATH) ?: $rawUrl, '/');
        $query = parse_url($rawUrl, PHP_URL_QUERY);
        $url = '{{base_url}}/'.$path.($query ? '?'.$query : '');

        return [
            'name' => $name,
            'request' => [
                'method' => 'GET',
                'header' => $this->authHeaders(),
                'url' => $url,
                'description' => $description,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPostOrderRequest(
        string $name,
        string $productIdVariable,
        string $description,
        string $orderType = 'simple',
    ): array {
        $query = match ($orderType) {
            'denomination' => $this->denominationOrderQueryParams($productIdVariable),
            'direct_topup' => $this->directTopupOrderQueryParams($productIdVariable),
            default => $this->simpleOrderQueryParams($productIdVariable),
        };

        return [
            'name' => $name,
            'request' => [
                'method' => 'POST',
                'header' => array_merge($this->authHeaders(), [
                    ['key' => 'X-Idempotency-Key', 'value' => 'order-{{$guid}}'],
                ]),
                'url' => $this->buildUrlWithQuery('api/v1/partner/orders', $query),
                'description' => $description,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPostQuoteRequest(
        string $name,
        string $productIdVariable,
        string $description,
        string $quoteType = 'simple',
    ): array {
        $query = $quoteType === 'denomination'
            ? $this->denominationQuoteQueryParams()
            : $this->simpleQuoteQueryParams();

        return [
            'name' => $name,
            'request' => [
                'method' => 'POST',
                'header' => $this->authHeaders(),
                'url' => $this->buildUrlWithQuery('api/v1/partner/products/'.$productIdVariable.'/quote', $query),
                'description' => $description,
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function normalizeCollectionUrls(array &$items): void
    {
        foreach ($items as &$item) {
            if (isset($item['request']['url']) && is_array($item['request']['url'])) {
                $url = $item['request']['url'];

                if (! isset($url['query']) && isset($url['raw'])) {
                    $item['request']['url'] = $url['raw'];
                }
            }

            if (! empty($item['item']) && is_array($item['item'])) {
                $this->normalizeCollectionUrls($item['item']);
            }
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function authHeaders(): array
    {
        return [
            ['key' => 'X-API-KEY', 'value' => '{{api_key}}'],
            ['key' => 'X-API-SECRET', 'value' => '{{api_secret}}'],
        ];
    }
}
