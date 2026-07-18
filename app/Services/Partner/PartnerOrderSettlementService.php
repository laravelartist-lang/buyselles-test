<?php

namespace App\Services\Partner;

use App\Models\AdminWallet;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Shop;
use App\Utils\OrderManager;
use Illuminate\Support\Facades\DB;

class PartnerOrderSettlementService
{
    /**
     * Persist partner order economics and credit the admin wallet.
     *
     * @param  array<string, mixed>  $quote
     */
    public function settle(Order $order, OrderDetail $orderDetail, array $quote, Product $product): void
    {
        $adminMargin = (float) $quote['admin_margin'];
        $serviceFee = (float) $quote['service_fee'];
        $total = (float) $quote['total'];
        $catalogSubtotal = (float) $quote['catalog_subtotal'];
        $sellerIs = $product->added_by === 'admin' ? 'admin' : 'seller';

        $order->update([
            'order_amount' => $total,
            'admin_commission' => $adminMargin,
            'customer_service_fee' => $serviceFee,
            'customer_service_fee_type' => (string) $quote['service_fee_type'],
            'seller_is' => $sellerIs,
        ]);

        $orderDetail->update([
            'partner_supplier_api_id' => $quote['supplier']['id'] ?? null,
            'partner_supplier_product_mapping_id' => $quote['supplier']['mapping_id'] ?? null,
            'partner_supplier_cost_total' => (float) $quote['supplier_cost_total'],
            'partner_admin_margin' => $adminMargin,
        ]);

        OrderManager::getCheckOrCreateAdminWallet();

        $shopId = null;

        if (\Illuminate\Support\Facades\Schema::hasTable('shops')) {
            $shop = Shop::query()
                ->when($sellerIs === 'admin', fn ($query) => $query->where('author_type', 'admin'))
                ->when($sellerIs === 'seller', fn ($query) => $query->where([
                    'author_type' => 'vendor',
                    'seller_id' => $order->seller_id,
                ]))
                ->first();

            $shopId = $shop?->id;
        }

        DB::table('order_transactions')->insert([
            'transaction_id' => OrderManager::generateUniqueOrderID(),
            'customer_id' => $order->customer_id,
            'seller_id' => $order->seller_id ?? $product->user_id,
            'shop_id' => $shopId,
            'seller_is' => $sellerIs,
            'order_id' => $order->id,
            'order_amount' => $total,
            'seller_amount' => $catalogSubtotal - $adminMargin,
            'admin_commission' => $adminMargin + $serviceFee,
            'received_by' => 'admin',
            'status' => 'disburse',
            'delivery_charge' => 0,
            'tax' => 0,
            'delivered_by' => 'admin',
            'payment_method' => $order->payment_method,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AdminWallet::query()
            ->where('admin_id', 1)
            ->increment('commission_earned', $adminMargin + $serviceFee);
    }

    public function reverseSettlement(Order $order): bool
    {
        if ($order->payment_method !== 'partner_wallet') {
            return false;
        }

        $adminMargin = (float) $order->admin_commission;
        $serviceFee = (float) ($order->customer_service_fee ?? 0);
        $reversalAmount = $adminMargin + $serviceFee;

        if ($reversalAmount <= 0) {
            return false;
        }

        OrderManager::getCheckOrCreateAdminWallet();

        AdminWallet::query()
            ->where('admin_id', 1)
            ->decrement('commission_earned', $reversalAmount);

        DB::table('order_transactions')
            ->where('order_id', $order->id)
            ->update([
                'status' => 'refunded',
                'updated_at' => now(),
            ]);

        $order->update([
            'admin_commission' => 0,
            'customer_service_fee' => 0,
        ]);

        return true;
    }
}
