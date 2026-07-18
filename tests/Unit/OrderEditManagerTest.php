<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Services\CommissionService;
use App\Traits\OrderEditManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

final class OrderEditManagerHarness
{
    use OrderEditManager;
}

class OrderEditManagerTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function usesDestructiveDatabaseSchemaChanges(): bool
    {
        return true;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->recreateTable('business_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->text('value')->nullable();
        });

        $this->recreateTable('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('product_type')->default('digital');
            $table->boolean('partner_api_only')->default(false);
            $table->decimal('unit_price', 24, 4)->default(0);
            $table->integer('current_stock')->default(0);
            $table->text('variation')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('tax_additional_setups', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('system_tax_setup_id')->nullable();
            $table->boolean('is_active')->default(1);
            $table->timestamps();
        });

        $this->recreateTable('system_tax_setups', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_active')->default(1);
            $table->boolean('is_default')->default(1);
            $table->string('tax_payer')->nullable();
            $table->string('tax_type')->nullable();
            $table->boolean('is_included')->default(0);
            $table->timestamps();
        });

        $this->recreateTable('shipping_methods', function (Blueprint $table): void {
            $table->id();
            $table->decimal('cost', 24, 4)->default(0);
            $table->timestamps();
        });

        $this->recreateTable('shipping_types', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('seller_id')->default(0);
            $table->string('shipping_type')->default('order_wise');
            $table->timestamps();
        });

        $this->recreateTable('reviews', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('delivery_man_id')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        $this->recreateTable('translations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('translationable_id')->nullable();
            $table->string('translationable_type')->nullable();
            $table->string('locale')->nullable();
            $table->string('key')->nullable();
            $table->text('value')->nullable();
        });

        $this->recreateTable('coupons', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->unsignedBigInteger('customer_id')->default(0);
            $table->string('code')->nullable();
            $table->string('coupon_type')->nullable();
            $table->decimal('discount', 24, 4)->default(0);
            $table->date('start_date')->nullable();
            $table->date('expire_date')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        $this->app['db']->table('business_settings')->insert([
            [
                'type' => 'language',
                'value' => json_encode([['code' => 'en', 'default' => true, 'direction' => 'ltr']]),
            ],
            ['type' => 'shipping_method', 'value' => 'inhouse_shipping'],
            ['type' => 'decimal_point_settings', 'value' => '2'],
            ['type' => 'currency_symbol_position', 'value' => 'left'],
            ['type' => 'system_default_currency', 'value' => '1'],
        ]);

        $this->mock(CommissionService::class, function ($mock): void {
            $mock->shouldReceive('calculate')->andReturn(0);
        });
    }

    public function test_generate_edit_order_summary_handles_partner_snapshot_without_current_stock(): void
    {
        $this->app['db']->table('products')->insert([
            'id' => 1,
            'name' => 'Partner Product',
            'product_type' => 'digital',
            'unit_price' => 10,
            'current_stock' => 8,
            'variation' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $snapshot = [
            'id' => 1,
            'name' => 'Partner Product',
            'product_type' => 'digital',
            'pricing' => ['total' => 10],
        ];

        $order = new Order([
            'id' => 100,
            'seller_is' => 'admin',
            'seller_id' => null,
            'customer_id' => 0,
            'is_guest' => 1,
            'order_amount' => 10,
            'shipping_cost' => 0,
            'shipping_method_id' => null,
            'coupon_code' => null,
            'refer_and_earn_discount' => 0,
        ]);
        $order->setRelation('details', new Collection([
            new OrderDetail([
                'product_id' => 1,
                'variant' => '',
                'qty' => 1,
            ]),
        ]));

        $editedOrder = [[
            'product_id' => 1,
            'variant' => '',
            'qty' => 1,
            'price' => 10,
            'discount' => 0,
            'product_type' => 'digital',
            'product_details' => json_encode($snapshot),
            'active_product' => $snapshot,
            'seller_id' => null,
            'seller_is' => 'admin',
        ]];

        $summary = (new OrderEditManagerHarness)->generateEditOrderSummary(
            request: [],
            order: $order,
            editedOrder: $editedOrder,
            data: [],
        );

        $this->assertSame('success', $summary['status']);
        $this->assertArrayHasKey('order_amount', $summary);
    }
}
