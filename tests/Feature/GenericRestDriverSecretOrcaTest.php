<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\SupplierApi;
use App\Services\Supplier\Drivers\GenericRestDriver;
use App\Services\Supplier\SupplierCatalogSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class GenericRestDriverSecretOrcaTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recreateTable('business_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('supplier_apis', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('driver')->default('generic_rest');
            $table->string('base_url')->nullable();
            $table->string('auth_type')->default('api_key');
            $table->text('credentials')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        BusinessSetting::query()->create(['type' => 'decimal_point_settings', 'value' => '2']);
    }

    /**
     * @return array<string, mixed>
     */
    private function secretOrcaSettings(): array
    {
        return [
            'api_key_header' => 'X-API-Key',
            'products_endpoint' => '/api/v1/external/catalog/products/',
            'products_response_path' => 'results',
            'pagination_total_path' => 'count',
            'pagination_page_base' => '1',
            'pagination_page_param' => 'page',
            'pagination_per_page_param' => 'page_size',
            'product_id_field' => 'id',
            'product_name_field' => 'name',
            'product_price_field' => 'unit_price',
            'product_category_field' => 'bot_name',
            'product_region_field' => 'code',
            'product_stock_default' => '999999',
            'price_decimal_places' => '10',
            'source_currency' => 'USD',
        ];
    }

    public function test_fetch_products_parses_drf_paginated_response(): void
    {
        Http::fake([
            'secretorca.test/api/v1/external/catalog/products*' => Http::response([
                'count' => 2,
                'next' => null,
                'previous' => null,
                'results' => [
                    [
                        'id' => '08214d2b-92f6-49f0-9d8c-3b5069e9d659',
                        'name' => 'Pary Star (MENA)',
                        'unit_price' => '0.0010628340',
                        'bot_name' => 'Channel 1',
                        'code' => 'PARTY_STAR_MENA',
                        'currency' => 'USD',
                    ],
                    [
                        'id' => '0d3b2fe8-1e2e-47f9-bad6-be50215ab465',
                        'name' => 'TANGO',
                        'unit_price' => '0.0050898356',
                        'bot_name' => 'Channel 1',
                        'code' => 'TANGO_GLOBAL',
                        'currency' => 'USD',
                    ],
                ],
            ]),
        ]);

        $supplier = new SupplierApi([
            'name' => 'SecretOrca',
            'driver' => 'generic_rest',
            'base_url' => 'https://secretorca.test',
            'auth_type' => 'api_key',
            'settings' => $this->secretOrcaSettings(),
            'is_active' => true,
        ]);
        $supplier->id = 1;
        $supplier->setEncryptedCredentials(['api_key' => 'sk_test_example']);

        $driver = app(GenericRestDriver::class)->configure($supplier);
        $products = $driver->fetchProducts(['page' => 1, 'page_size' => 100, 'fetch_all' => false]);

        $this->assertCount(2, $products);
        $this->assertSame('08214d2b-92f6-49f0-9d8c-3b5069e9d659', $products[0]->supplierProductId);
        $this->assertSame('Pary Star (MENA)', $products[0]->name);
        $this->assertEqualsWithDelta(0.001062834, $products[0]->price, 0.0000001);
        $this->assertSame(999999, $products[0]->stockAvailable);
        $this->assertSame('PARTY_STAR_MENA', $products[0]->region);
    }

    public function test_fetch_products_throws_on_invalid_page_response(): void
    {
        Http::fake([
            'secretorca.test/api/v1/external/catalog/products*' => Http::response([
                'success' => false,
                'error' => ['code' => 404, 'detail' => ['detail' => 'Invalid page.']],
            ], 404),
        ]);

        $supplier = new SupplierApi([
            'name' => 'SecretOrca',
            'driver' => 'generic_rest',
            'base_url' => 'https://secretorca.test',
            'auth_type' => 'api_key',
            'settings' => $this->secretOrcaSettings(),
            'is_active' => true,
        ]);
        $supplier->id = 1;
        $supplier->setEncryptedCredentials(['api_key' => 'sk_test_example']);

        $driver = app(GenericRestDriver::class)->configure($supplier);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('fetchProducts failed: HTTP 404');

        $driver->fetchProducts(['page' => 0, 'page_size' => 100, 'fetch_all' => false]);
    }

    public function test_catalog_sync_uses_one_based_page_numbers(): void
    {
        Http::fake([
            'secretorca.test/api/v1/external/catalog/products*' => function ($request) {
                $page = (int) $request->data()['page'];

                if ($page === 0) {
                    return Http::response([
                        'success' => false,
                        'error' => ['code' => 404, 'detail' => ['detail' => 'Invalid page.']],
                    ], 404);
                }

                return Http::response([
                    'count' => 1,
                    'next' => null,
                    'previous' => null,
                    'results' => [
                        [
                            'id' => 'abc',
                            'name' => 'Test Product',
                            'unit_price' => '1.00',
                            'bot_name' => 'Channel 1',
                            'code' => 'TEST',
                            'currency' => 'USD',
                        ],
                    ],
                ]);
            },
        ]);

        $supplier = SupplierApi::query()->create([
            'name' => 'SecretOrca',
            'driver' => 'generic_rest',
            'base_url' => 'https://secretorca.test',
            'auth_type' => 'api_key',
            'settings' => $this->secretOrcaSettings(),
            'is_active' => true,
        ]);
        $supplier->setEncryptedCredentials(['api_key' => 'sk_test_example']);
        $supplier->save();

        $service = app(SupplierCatalogSyncService::class);
        $service->beginSync($supplier->id, freshStart: true);

        $result = $service->syncPage($supplier->id, 0);

        $this->assertFalse($result->hasMorePages);
        $this->assertSame(1, $result->itemsOnPage);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/catalog/products/')
                && ($request->data()['page'] ?? null) === 1;
        });
    }
}
