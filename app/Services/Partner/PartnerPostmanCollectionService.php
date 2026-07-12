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
                'description' => 'Your Partner API key (rslr_...)',
            ],
            [
                'key' => 'api_secret',
                'value' => '',
                'type' => 'string',
                'description' => 'Your Partner API secret',
            ],
            [
                'key' => 'last_order_id',
                'value' => '',
                'type' => 'string',
                'description' => 'Auto-populated after a successful order creation',
            ],
            [
                'key' => 'last_product_id',
                'value' => $samples['in_house_local'] !== null ? (string) $samples['in_house_local'] : '',
                'type' => 'string',
                'description' => 'Sample in-house product ID from your catalog',
            ],
            [
                'key' => 'example_in_house_local_product_id',
                'value' => $samples['in_house_local'] !== null ? (string) $samples['in_house_local'] : '',
                'type' => 'string',
                'description' => 'In-house product fulfilled from local code pool',
            ],
            [
                'key' => 'example_supplier_mapped_product_id',
                'value' => $samples['supplier_mapped'] !== null ? (string) $samples['supplier_mapped'] : '',
                'type' => 'string',
                'description' => 'In-house product mapped to Bamboo/Golf supplier',
            ],
            [
                'key' => 'example_vendor_product_id',
                'value' => $samples['vendor'] !== null ? (string) $samples['vendor'] : '',
                'type' => 'string',
                'description' => 'Vendor product (requires include_vendor=1)',
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
            'description' => 'Pre-filled requests using sample product IDs from your current partner-approved catalog. Regenerate by downloading the collection again from Admin > API Documentation.',
            'item' => [
                $this->buildGetRequest(
                    'List Products — in-house only (default)',
                    '/api/v1/partner/products',
                    'Returns only in-house (admin) digital products. Vendor products are excluded unless include_vendor=1 is passed.'
                ),
                $this->buildGetRequest(
                    'List Products — include vendor',
                    '/api/v1/partner/products?include_vendor=1',
                    'Includes partner-approved vendor products in addition to in-house products.'
                ),
                $this->buildGetRequest(
                    'List Products — supplier-mapped only',
                    '/api/v1/partner/products?fulfillment_type=supplier_codes',
                    'Only products fulfilled via upstream suppliers (Bamboo, Golf API, etc.).'
                ),
                $this->buildGetRequest(
                    'Get Product — in-house local',
                    '/api/v1/partner/products/{{example_in_house_local_product_id}}',
                    'Detail for an in-house product with local code pool fulfillment.'
                ),
                $this->buildGetRequest(
                    'Get Product — supplier mapped',
                    '/api/v1/partner/products/{{example_supplier_mapped_product_id}}',
                    'Detail for a supplier-mapped in-house product. Check fulfillment_type and supplier fields.'
                ),
                $this->buildPostOrderRequest(
                    'Create Order — in-house local product',
                    '{{example_in_house_local_product_id}}',
                    'Order a product fulfilled from the local code pool. Expect status fulfilled with codes in response.'
                ),
                $this->buildPostOrderRequest(
                    'Create Order — supplier-mapped product',
                    '{{example_supplier_mapped_product_id}}',
                    'Order a supplier-backed product. May return status pending_fulfillment — poll GET /orders/{id} for codes.'
                ),
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
    private function buildPostOrderRequest(string $name, string $productIdVariable, string $description): array
    {
        return [
            'name' => $name,
            'request' => [
                'method' => 'POST',
                'header' => array_merge($this->authHeaders(), [
                    ['key' => 'Content-Type', 'value' => 'application/json'],
                    ['key' => 'X-Idempotency-Key', 'value' => 'order-{{$guid}}'],
                ]),
                'body' => [
                    'mode' => 'raw',
                    'raw' => "{\n  \"product_id\": {{$productIdVariable}},\n  \"quantity\": 1,\n  \"reference\": \"postman-example\"\n}",
                    'options' => ['raw' => ['language' => 'json']],
                ],
                'url' => '{{base_url}}/api/v1/partner/orders',
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
                $item['request']['url'] = $item['request']['url']['raw']
                    ?? '{{base_url}}';
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
