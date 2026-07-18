<?php

namespace Tests\Unit;

use App\DTOs\Supplier\SupplierOrderResult;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\SupplierOrder;
use App\Models\SupplierProductMapping;
use App\Services\Partner\PartnerSupplierFulfillmentService;
use App\Services\Supplier\SupplierManager;
use Mockery;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\Concerns\SetsUpPartnerApiTestSchema;
use Tests\TestCase;

class PartnerSupplierFulfillmentServiceTest extends TestCase
{
    use ManagesTestDatabaseSchema;
    use SetsUpPartnerApiTestSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPartnerApiSchema();
    }

    public function test_supplier_first_fulfillment_fetches_and_assigns_codes_from_poll(): void
    {
        $this->app['db']->table('products')->insert([
            'id' => 90,
            'user_id' => 1,
            'added_by' => 'admin',
            'name' => 'Sync Product',
            'slug' => 'sync-product',
            'product_type' => 'digital',
            'digital_product_type' => 'ready_product',
            'status' => 1,
            'request_status' => 1,
            'partner_approved' => true,
            'unit_price' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $supplierId = $this->app['db']->table('supplier_apis')->insertGetId([
            'name' => 'Bamboo',
            'driver' => 'bamboo',
            'base_url' => 'https://supplier.test',
            'credentials' => '{}',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mappingId = $this->app['db']->table('supplier_product_mappings')->insertGetId([
            'product_id' => 90,
            'supplier_api_id' => $supplierId,
            'supplier_product_id' => 'sku-90',
            'cost_price' => 7,
            'code_source_priority' => SupplierProductMapping::CODE_SOURCE_SUPPLIER_FIRST,
            'is_active' => true,
            'is_direct_topup' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mapping = SupplierProductMapping::query()->find($mappingId);
        $this->assertTrue($mapping->isSupplierFirst());

        $driver = Mockery::mock(\App\Contracts\SupplierDriverInterface::class);
        $driver->shouldReceive('getOrderStatus')
            ->andReturn(new SupplierOrderResult(
                supplierOrderId: 'bamboo-request-456',
                status: 'fulfilled',
                codes: [['code' => 'SYNC-CODE-1']],
            ));

        $manager = Mockery::mock(SupplierManager::class);
        $manager->shouldReceive('driver')->andReturn($driver);
        $manager->shouldReceive('fetchAndStockCodes')
            ->once()
            ->andReturnUsing(function () use ($mappingId, $supplierId): array {
                $supplierOrderId = SupplierOrder::query()->create([
                    'supplier_api_id' => $supplierId,
                    'supplier_product_mapping_id' => $mappingId,
                    'supplier_order_id' => 'bamboo-request-456',
                    'quantity' => 1,
                    'cost_per_unit' => 7,
                    'total_cost' => 7,
                    'cost_currency' => 'USD',
                    'status' => 'processing',
                ])->id;

                return [
                    'inserted' => 0,
                    'supplier_id' => $supplierId,
                    'supplier_order_id' => $supplierOrderId,
                ];
            });
        $this->app->forgetInstance(SupplierManager::class);
        $this->app->forgetInstance(\App\Services\Partner\PartnerSupplierFulfillmentService::class);
        $this->app->instance(SupplierManager::class, $manager);

        $order = Order::query()->create([
            'payment_status' => 'paid',
            'order_status' => 'processing',
            'payment_method' => 'partner_wallet',
            'order_amount' => 10,
        ]);

        OrderDetail::query()->create([
            'order_id' => $order->id,
            'product_id' => 90,
            'seller_id' => 1,
            'product_details' => json_encode([
                'id' => 90,
                'product_type' => 'digital',
                'digital_product_type' => 'ready_product',
            ]),
            'qty' => 1,
            'price' => 10,
            'payment_status' => 'paid',
            'delivery_status' => 'pending',
            'product_type' => 'digital',
            'digital_product_type' => 'ready_product',
        ]);

        $order->load('orderDetails');

        $result = app(PartnerSupplierFulfillmentService::class)->fulfillSynchronously($order);

        $this->assertTrue($result['success'], json_encode($result));
        $this->assertFalse($result['pending']);
        $this->assertSame(1, \App\Models\DigitalProductCode::query()
            ->where('order_id', $order->id)
            ->where('status', 'sold')
            ->count());
    }

    public function test_mapped_product_fulfillment_service_uses_supplier_first_sync_path(): void
    {
        $this->app['db']->table('products')->insert([
            'id' => 91,
            'user_id' => 1,
            'added_by' => 'admin',
            'name' => 'Mapped Sync Product',
            'slug' => 'mapped-sync-product',
            'product_type' => 'digital',
            'digital_product_type' => 'ready_product',
            'status' => 1,
            'request_status' => 1,
            'partner_approved' => true,
            'unit_price' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $supplierId = $this->app['db']->table('supplier_apis')->insertGetId([
            'name' => 'Bamboo',
            'driver' => 'bamboo',
            'base_url' => 'https://supplier.test',
            'credentials' => '{}',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mappingId = $this->app['db']->table('supplier_product_mappings')->insertGetId([
            'product_id' => 91,
            'supplier_api_id' => $supplierId,
            'supplier_product_id' => 'sku-91',
            'cost_price' => 7,
            'code_source_priority' => SupplierProductMapping::CODE_SOURCE_SUPPLIER_FIRST,
            'is_active' => true,
            'is_direct_topup' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $driver = Mockery::mock(\App\Contracts\SupplierDriverInterface::class);
        $driver->shouldReceive('getOrderStatus')
            ->andReturn(new SupplierOrderResult(
                supplierOrderId: 'bamboo-request-789',
                status: 'fulfilled',
                codes: [['code' => 'MAPPED-SYNC-CODE']],
            ));

        $this->mock(SupplierManager::class, function ($manager) use ($mappingId, $supplierId, $driver): void {
            $manager->shouldReceive('fetchAndStockCodes')
                ->once()
                ->andReturnUsing(function () use ($mappingId, $supplierId): array {
                    $supplierOrderId = SupplierOrder::query()->create([
                        'supplier_api_id' => $supplierId,
                        'supplier_product_mapping_id' => $mappingId,
                        'supplier_order_id' => 'bamboo-request-789',
                        'quantity' => 1,
                        'cost_per_unit' => 7,
                        'total_cost' => 7,
                        'cost_currency' => 'USD',
                        'status' => 'processing',
                    ])->id;

                    return [
                        'inserted' => 0,
                        'supplier_id' => $supplierId,
                        'supplier_order_id' => $supplierOrderId,
                    ];
                });
            $manager->shouldReceive('driver')->andReturn($driver);
        });

        $order = Order::query()->create([
            'payment_status' => 'paid',
            'order_status' => 'processing',
            'payment_method' => 'partner_wallet',
            'order_amount' => 10,
        ]);

        OrderDetail::query()->create([
            'order_id' => $order->id,
            'product_id' => 91,
            'seller_id' => 1,
            'product_details' => json_encode([
                'id' => 91,
                'product_type' => 'digital',
                'digital_product_type' => 'ready_product',
            ]),
            'qty' => 1,
            'price' => 10,
            'payment_status' => 'paid',
            'delivery_status' => 'pending',
            'product_type' => 'digital',
            'digital_product_type' => 'ready_product',
        ]);

        $order->load('orderDetails');

        $result = app(\App\Services\Supplier\MappedProductFulfillmentService::class)->fulfillPartnerOrder($order);

        $this->assertFalse($result['failed'], json_encode($result));
        $this->assertFalse($result['pending'], json_encode($result));
        $this->assertSame(1, \App\Models\DigitalProductCode::query()
            ->where('order_id', $order->id)
            ->where('status', 'sold')
            ->count());
    }
}
