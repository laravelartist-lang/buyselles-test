<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\SellerWallet;
use App\Models\SellerWalletHistory;
use App\Services\Partner\PartnerOrderRefundService;
use Illuminate\Database\Schema\Blueprint;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\Concerns\SetsUpPartnerApiTestSchema;
use Tests\TestCase;

class PartnerVendorWalletRefundTest extends TestCase
{
    use ManagesTestDatabaseSchema;
    use SetsUpPartnerApiTestSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPartnerApiSchema();
        $this->recreateTable('seller_wallet_histories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->decimal('amount', 24, 4)->default(0);
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('payment')->nullable();
            $table->timestamps();
        });
    }

    public function test_vendor_partner_refund_is_idempotent(): void
    {
        $sellerId = 1;
        $this->seedVendorPartnerApiKey($sellerId, totalEarning: 475);

        $order = Order::query()->create([
            'customer_id' => null,
            'customer_type' => 'partner',
            'seller_id' => $sellerId,
            'payment_method' => 'partner_wallet',
            'payment_status' => 'paid',
            'order_status' => 'failed',
            'order_amount' => 25,
            'order_type' => 'default',
            'is_guest' => 0,
        ]);

        $this->app['db']->table('order_transactions')->insert([
            'transaction_id' => 'txn-refund-test',
            'seller_id' => $sellerId,
            'order_id' => $order->id,
            'order_amount' => 25,
            'admin_commission' => 3,
            'status' => 'pending_disburse',
            'payment_method' => 'partner_wallet',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $refundService = app(PartnerOrderRefundService::class);

        $this->assertTrue($refundService->refundPaidPartnerOrder($order));
        $this->assertFalse($refundService->refundPaidPartnerOrder($order->fresh()));
        $this->assertTrue($refundService->hasRefundForOrder($order->fresh()));

        $this->assertSame(500.0, (float) SellerWallet::where('seller_id', $sellerId)->value('total_earning'));
        $this->assertSame(1, SellerWalletHistory::query()
            ->where('seller_id', $sellerId)
            ->where('order_id', $order->id)
            ->where('payment', 'partner_api_order_refund')
            ->count());
    }
}
