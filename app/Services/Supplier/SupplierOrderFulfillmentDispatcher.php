<?php

namespace App\Services\Supplier;

use App\Jobs\DirectTopUpFulfillmentJob;
use App\Jobs\SupplierCodeFetchJob;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class SupplierOrderFulfillmentDispatcher
{
    public function __construct(
        private readonly SupplierOrderEligibilityService $supplierOrderEligibilityService,
    ) {}

    public function dispatchForOrderId(int $orderId): void
    {
        $order = Order::query()->with('orderDetails')->find($orderId);

        if ($order === null) {
            Log::warning('SupplierOrderFulfillmentDispatcher: order not found', [
                'order_id' => $orderId,
            ]);

            return;
        }

        $this->dispatchForOrder($order);
    }

    public function dispatchForOrder(Order $order): void
    {
        $this->dispatchSupplierCodeFetchIfNeeded($order);
        $this->dispatchDirectTopUpIfNeeded($order);
    }

    private function dispatchSupplierCodeFetchIfNeeded(Order $order): void
    {
        try {
            if ($this->supplierOrderEligibilityService->orderNeedsSupplierCodeFetch($order)) {
                SupplierCodeFetchJob::dispatch($order->id);
                Log::info('SupplierOrderFulfillmentDispatcher: dispatched SupplierCodeFetchJob', [
                    'order_id' => $order->id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('SupplierOrderFulfillmentDispatcher: supplier code fetch dispatch failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function dispatchDirectTopUpIfNeeded(Order $order): void
    {
        try {
            if (! $this->supplierOrderEligibilityService->orderNeedsDirectTopUpFulfillment($order)) {
                return;
            }

            DirectTopUpFulfillmentJob::dispatch($order->id);
            Log::info('SupplierOrderFulfillmentDispatcher: dispatched DirectTopUpFulfillmentJob', [
                'order_id' => $order->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('SupplierOrderFulfillmentDispatcher: direct top-up dispatch failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
