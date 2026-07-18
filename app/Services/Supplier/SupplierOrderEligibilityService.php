<?php

namespace App\Services\Supplier;

use App\Models\DigitalProductCode;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\SupplierOrder;
use App\Models\SupplierProductMapping;
use App\Services\DirectTopUp\DirectTopUpWalletCheckoutService;

class SupplierOrderEligibilityService
{
    public function orderNeedsSupplierCodeFetch(Order $order): bool
    {
        $order->loadMissing('orderDetails');

        foreach ($order->orderDetails ?? [] as $detail) {
            if ($this->orderDetailNeedsSupplierCodeFetch($detail)) {
                return true;
            }
        }

        return false;
    }

    public function orderNeedsDirectTopUpFulfillment(Order $order): bool
    {
        if (DirectTopUpWalletCheckoutService::isDirectTopUpAlreadyFulfilled($order)) {
            return false;
        }

        if (SupplierOrder::query()
            ->where('order_id', $order->id)
            ->whereIn('status', ['pending', 'processing', 'partial'])
            ->exists()) {
            return false;
        }

        $order->loadMissing('orderDetails');

        foreach ($order->orderDetails ?? [] as $detail) {
            if ($this->isDirectTopUpOrderLine($detail)) {
                return true;
            }
        }

        return false;
    }

    public function orderDetailNeedsSupplierCodeFetch(OrderDetail $detail): bool
    {
        $productDetails = json_decode($detail->product_details ?? '{}');
        $productType = $productDetails->product_type ?? null;

        if ($productType !== 'digital') {
            return false;
        }

        $productId = (int) ($detail->product_id ?? ($productDetails->id ?? 0));

        if ($this->isDirectTopUpOrderLine($detail, $productId)) {
            return false;
        }

        if (! $this->isEligibleDigitalFulfillmentType($productDetails, $productId)) {
            return false;
        }

        if ($productId <= 0) {
            return false;
        }

        $assignedCount = DigitalProductCode::query()
            ->where('order_detail_id', $detail->id)
            ->where('status', 'sold')
            ->count();

        $needed = max(0, (int) $detail->qty - $assignedCount);

        if ($needed <= 0) {
            return false;
        }

        return $this->hasActiveSupplierMapping($productId);
    }

    public function isDirectTopUpOrderLine(OrderDetail $detail, ?int $productId = null): bool
    {
        if ($detail->direct_topup_quantity === null || empty($detail->direct_topup_account_id)) {
            return false;
        }

        $productId = $productId ?? (int) $detail->product_id;

        if ($productId <= 0) {
            return false;
        }

        return SupplierProductMapping::query()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->where('is_direct_topup', true)
            ->whereHas('supplierApi', fn ($q) => $q->where('is_active', true)->where('supports_direct_top_up', true))
            ->exists();
    }

    public function hasActiveSupplierMapping(int $productId): bool
    {
        return SupplierProductMapping::query()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->whereHas('supplierApi', fn ($q) => $q->where('is_active', true))
            ->exists();
    }

    private function isEligibleDigitalFulfillmentType(object|string|null $productDetails, int $productId): bool
    {
        if (is_string($productDetails)) {
            $productDetails = json_decode($productDetails) ?? new \stdClass;
        }

        $digitalType = $productDetails->digital_product_type ?? null;

        if (in_array($digitalType, ['ready_product', 'ready_after_sell'], true)) {
            return true;
        }

        return $this->hasActiveSupplierMapping($productId);
    }
}
