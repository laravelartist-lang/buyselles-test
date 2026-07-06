<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Currency;
use App\Models\SupplierApi;
use App\Services\Supplier\Drivers\GenericRestDriver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class GenericRestDriverJodConversionTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recreateTable('currencies', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('symbol')->nullable();
            $table->string('code', 10);
            $table->decimal('exchange_rate', 14, 6)->default(1);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

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
            $table->string('auth_type')->default('bearer_token');
            $table->text('credentials')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $usd = Currency::query()->create([
            'name' => 'USD',
            'symbol' => '$',
            'code' => 'USD',
            'exchange_rate' => 1,
            'status' => true,
        ]);

        Currency::query()->create([
            'name' => 'Jordanian Dinar',
            'symbol' => 'JOD',
            'code' => 'JOD',
            'exchange_rate' => 0.709,
            'status' => true,
        ]);

        BusinessSetting::query()->create(['type' => 'currency_model', 'value' => 'multi_currency']);
        BusinessSetting::query()->create(['type' => 'system_default_currency', 'value' => (string) $usd->id]);
        BusinessSetting::query()->create(['type' => 'decimal_point_settings', 'value' => '2']);
    }

    public function test_fetch_products_preserves_jod_prices_from_api(): void
    {
        Http::fake([
            'golf-test.example/api/products*' => Http::response([
                'data' => [
                    [
                        'id' => 42,
                        'name' => 'Orange 1 JD',
                        'price' => 1.709,
                        'stock' => 10,
                    ],
                ],
            ]),
        ]);

        $supplier = new SupplierApi([
            'name' => 'Golf Generic REST',
            'driver' => 'generic_rest',
            'base_url' => 'https://golf-test.example/api',
            'auth_type' => 'bearer_token',
            'settings' => [
                'source_currency' => 'JOD',
                'products_endpoint' => '/products',
                'products_response_path' => 'data',
            ],
            'is_active' => true,
        ]);
        $supplier->id = 1;
        $supplier->setEncryptedCredentials(['api_key' => 'test-token']);

        $driver = app(GenericRestDriver::class)->configure($supplier);
        $products = $driver->fetchProducts(['page' => 1, 'fetch_all' => false]);

        $this->assertCount(1, $products);
        $this->assertSame('JOD', $products[0]->currency);
        $this->assertEqualsWithDelta(1.709, $products[0]->price, 0.001);
        $this->assertSame(1.709, $products[0]->rawData['price']);
    }

    public function test_fetch_stock_preserves_jod_price_from_api(): void
    {
        Http::fake([
            'golf-test.example/api/products/42' => Http::response([
                'data' => [
                    'stock' => 5,
                    'price' => 1.62,
                ],
            ]),
        ]);

        $supplier = new SupplierApi([
            'name' => 'Golf Generic REST',
            'driver' => 'generic_rest',
            'base_url' => 'https://golf-test.example/api',
            'auth_type' => 'bearer_token',
            'settings' => [
                'source_currency' => 'JOD',
                'stock_endpoint' => '/products/{product_id}',
                'stock_response_path' => 'data.stock',
                'stock_price_path' => 'data.price',
            ],
            'is_active' => true,
        ]);
        $supplier->id = 1;
        $supplier->setEncryptedCredentials(['api_key' => 'test-token']);

        $driver = app(GenericRestDriver::class)->configure($supplier);
        $stock = $driver->fetchStock('42');

        $this->assertSame('JOD', $stock->currency);
        $this->assertSame(1.62, $stock->price);
    }
}
