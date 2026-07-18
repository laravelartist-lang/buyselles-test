<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Services\OrderEditService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class OrderEditServiceTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function usesDestructiveDatabaseSchemaChanges(): bool
    {
        return true;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->recreateTable('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->string('product_type')->default('digital');
            $table->string('digital_product_type')->default('ready_product');
            $table->boolean('partner_api_only')->default(false);
            $table->decimal('unit_price', 24, 4)->default(0);
            $table->integer('current_stock')->default(0);
            $table->integer('minimum_order_qty')->default(1);
            $table->text('variation')->nullable();
            $table->string('discount_type')->nullable();
            $table->decimal('discount', 24, 4)->default(0);
            $table->timestamps();
        });
    }

    public function test_add_order_details_in_session_uses_db_stock_when_snapshot_lacks_current_stock(): void
    {
        $snapshot = [
            'id' => 1,
            'name' => 'Partner Product',
            'slug' => 'partner-product',
            'product_type' => 'digital',
            'digital_product_type' => 'ready_product',
            'pricing' => ['total' => 10],
        ];

        $order = new Order(['id' => 100, 'seller_is' => 'admin']);
        $order->setRelation('details', new Collection([
            $this->makeOrderDetail(productId: 1, snapshot: $snapshot, qty: 2),
        ]));

        $dbProduct = [
            'id' => 1,
            'current_stock' => 5,
            'unit_price' => 10,
            'variation' => '[]',
            'discount_type' => 'flat',
            'discount' => 0,
        ];

        $service = $this->partialMock(OrderEditService::class, function ($mock) use ($dbProduct): void {
            $mock->shouldReceive('getProductListWithAllDetails')
                ->once()
                ->andReturn(collect([$dbProduct]));
        });

        $products = $service->addOrderDetailsInSession($order);

        $this->assertCount(1, $products);
        $product = array_values($products)[0];

        $this->assertSame(7, $product['current_stock']);
        $this->assertSame(10.0, (float) $product['price']);
        $this->assertSame('flat', $product['discount_type']);
    }

    public function test_add_order_details_in_session_defaults_stock_when_product_is_missing_from_catalog(): void
    {
        $snapshot = [
            'id' => 99,
            'name' => 'Deleted Partner Product',
            'product_type' => 'digital',
        ];

        $order = new Order(['id' => 101, 'seller_is' => 'admin']);
        $order->setRelation('details', new Collection([
            $this->makeOrderDetail(productId: 99, snapshot: $snapshot, qty: 3),
        ]));

        $service = $this->partialMock(OrderEditService::class, function ($mock): void {
            $mock->shouldReceive('getProductListWithAllDetails')
                ->once()
                ->andReturn(collect([]));
        });

        $products = $service->addOrderDetailsInSession($order);

        $this->assertCount(1, $products);
        $product = array_values($products)[0];

        $this->assertSame(3, $product['current_stock']);
        $this->assertSame('', $product['discount_type']);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function makeOrderDetail(int $productId, array $snapshot, int $qty): OrderDetail
    {
        return new OrderDetail([
            'product_id' => $productId,
            'order_id' => 1,
            'seller_id' => 1,
            'qty' => $qty,
            'price' => 10,
            'tax' => 0,
            'discount' => 0,
            'tax_model' => 'exclude',
            'delivery_status' => 'pending',
            'payment_status' => 'paid',
            'shipping_method_id' => null,
            'variant' => '',
            'variation' => '[]',
            'is_stock_decreased' => false,
            'refund_request' => 0,
            'digital_file_after_sell' => null,
            'product_details' => json_encode($snapshot),
            'created_at' => now(),
        ]);
    }
}
