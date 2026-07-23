<?php

namespace App\Services\Supplier;

use App\Models\Order;
use App\Models\SupplierOrder;
use App\Models\SupplierProductMapping;
use App\Services\DigitalProductCodeService;
use App\Services\Partner\PartnerSupplierFulfillmentService;

class MappedProductFulfillmentService
{
    public function __construct(
        private readonly DigitalProductCodeService $codeService,
        private readonly SupplierOrderFulfillmentDispatcher $fulfillmentDispatcher,
        private readonly SupplierOrderEligibilityService $eligibilityService,
        private readonly PartnerSupplierFulfillmentService $partnerSupplierFulfillment,
    ) {}

    public function fulfillStorefrontOrder(Order $order): void
    {
        if (! $this->eligibilityService->orderIsEligibleForAutomatedFulfillment($order)) {
            return;
        }

        $mapping = $this->resolvePrimaryCodeMapping($order);

        if ($mapping?->isSupplierFirst()) {
            $this->fulfillmentDispatcher->dispatchForOrder($order);

            return;
        }

        $this->codeService->assignAndNotify($order);
        $this->fulfillmentDispatcher->dispatchForOrder($order);
    }

    /**
     * @return array{
     *     pending: bool,
     *     failed: bool,
     *     error: ?string,
     *     supplier_order_id: ?string,
     *     supplier_request_id: ?string
     * }
     */
    public function fulfillPartnerOrder(Order $order): array
    {
        $mapping = $this->resolvePrimaryCodeMapping($order);

        if ($mapping === null) {
            $this->codeService->assignAndNotify($order);

            return [
                'pending' => false,
                'failed' => false,
                'error' => null,
                'supplier_order_id' => null,
                'supplier_request_id' => null,
            ];
        }

        if ($mapping->isSupplierFirst()) {
            $result = $this->partnerSupplierFulfillment->fulfillSynchronously($order);

            return [
                'pending' => (bool) ($result['pending'] ?? false),
                'failed' => (bool) ($result['failed'] ?? false),
                'error' => $result['error'] ?? null,
                'supplier_order_id' => $result['supplier_order_id'] ?? null,
                'supplier_request_id' => $result['supplier_request_id'] ?? null,
            ];
        }

        $this->codeService->assignAndNotify($order);

        return [
            'pending' => $this->orderNeedsAsyncSupplier($order),
            'failed' => false,
            'error' => null,
            'supplier_order_id' => null,
            'supplier_request_id' => null,
        ];
    }

    public function orderNeedsAsyncSupplier(Order $order): bool
    {
        if (! $this->eligibilityService->orderNeedsSupplierCodeFetch($order)) {
            return false;
        }

        return ! SupplierOrder::query()
            ->where('order_id', $order->id)
            ->whereIn('status', ['pending', 'processing', 'partial'])
            ->exists();
    }

    public function dispatchAsyncFallbackIfNeeded(Order $order): void
    {
        if (! $this->eligibilityService->orderIsEligibleForAutomatedFulfillment($order)) {
            return;
        }

        if ($this->orderNeedsAsyncSupplier($order) || $this->eligibilityService->orderNeedsDirectTopUpFulfillment($order)) {
            $this->fulfillmentDispatcher->dispatchForOrder($order);
        }
    }

    private function resolvePrimaryCodeMapping(Order $order): ?SupplierProductMapping
    {
        $order->loadMissing('orderDetails');

        foreach ($order->orderDetails ?? [] as $detail) {
            $productId = (int) ($detail->product_id ?? 0);

            if ($productId <= 0) {
                continue;
            }

            $mapping = SupplierProductMapping::query()
                ->where('product_id', $productId)
                ->where('is_active', true)
                ->where('is_direct_topup', false)
                ->whereHas('supplierApi', fn ($query) => $query->where('is_active', true))
                ->orderBy('priority')
                ->first();

            if ($mapping !== null) {
                return $mapping;
            }
        }

        return null;
    }
}
