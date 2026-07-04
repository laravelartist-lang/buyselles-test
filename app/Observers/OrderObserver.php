<?php

namespace App\Observers;

use App\Jobs\DirectTopUpFulfillmentJob;
use App\Jobs\SupplierCodeFetchJob;
use App\Models\DigitalProductCode;
use App\Models\Order;
use App\Models\SupplierProductMapping;
use App\Services\DigitalProductCodeService;
use App\Traits\PushNotificationTrait;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    use PushNotificationTrait;

    public function __construct(private readonly DigitalProductCodeService $codeService) {}

    /**
     * Handle the Order "created" event.
     * Some payment methods (wallet, free orders) create the order already in 'paid' status,
     * so the updated observer won't fire. We handle those here.
     */
    public function created(Order $order): void
    {
        if ($order->payment_status === 'paid') {
            try {
                $this->codeService->assignAndNotify($order);
            } catch (\Throwable $e) {
                Log::error('OrderObserver: digital code assignment/email failed on create', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $this->dispatchSupplierFallbackIfNeeded($order);
            $this->dispatchDirectTopUpIfNeeded($order);
        }
    }

    /**
     * Handle the Order "updated" event.
     * When payment_status transitions to 'paid', assign digital codes to all eligible order details
     * and then email them to the customer. If any digital order details remain unfulfilled and have
     * supplier mappings, dispatch a supplier fetch job to acquire codes from external suppliers.
     */
    public function updated(Order $order): void
    {
        if ($order->wasChanged('payment_status') && $order->payment_status === 'paid') {
            try {
                $this->codeService->assignAndNotify($order);
            } catch (\Throwable $e) {
                Log::error('OrderObserver: digital code assignment/email failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $this->dispatchSupplierFallbackIfNeeded($order);
            $this->dispatchDirectTopUpIfNeeded($order);
        }
    }

    /**
     * Check if any digital "ready_product" order details still need codes after local pool assignment.
     * If a product has active supplier mappings, dispatch a SupplierCodeFetchJob to acquire codes.
     */
    private function dispatchSupplierFallbackIfNeeded(Order $order): void
    {
        try {
            $order->loadMissing('orderDetails');

            if (! $order->orderDetails) {
                return;
            }

            $needsSupplierFetch = false;

            foreach ($order->orderDetails as $detail) {
                $productDetails = json_decode($detail->product_details ?? '{}');
                $productType = $productDetails->product_type ?? null;
                $digitalType = $productDetails->digital_product_type ?? null;
                $isDirectTopUp = ! empty($detail->direct_topup_quantity);

                if ($productType !== 'digital' || $isDirectTopUp) {
                    continue;
                }

                $productId = $detail->product_id ?? ($productDetails->id ?? null);
                if (! $productId) {
                    continue;
                }

                $assignedCount = DigitalProductCode::query()
                    ->where('order_detail_id', $detail->id)
                    ->where('status', 'sold')
                    ->count();

                $needed = max(0, (int) $detail->qty - $assignedCount);

                if ($needed <= 0) {
                    continue;
                }

                // Check if product has active supplier mappings
                $hasMapping = SupplierProductMapping::query()
                    ->where('product_id', $productId)
                    ->where('is_active', true)
                    ->whereHas('supplierApi', fn ($q) => $q->where('is_active', true))
                    ->exists();

                if ($hasMapping) {
                    $needsSupplierFetch = true;
                    break;
                }
            }

            if ($needsSupplierFetch) {
                SupplierCodeFetchJob::dispatch($order->id);
                Log::info('OrderObserver: dispatched SupplierCodeFetchJob for unfulfilled codes', [
                    'order_id' => $order->id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('OrderObserver: supplier fallback check failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function dispatchDirectTopUpIfNeeded(Order $order): void
    {
        try {
            $order->loadMissing('orderDetails');

            if (! $order->orderDetails) {
                return;
            }

            $needsDirectTopUp = false;

            foreach ($order->orderDetails as $detail) {
                if ($detail->direct_topup_quantity === null || empty($detail->direct_topup_account_id)) {
                    continue;
                }

                $productId = $detail->product_id;
                if (! $productId) {
                    continue;
                }

                $hasMapping = SupplierProductMapping::query()
                    ->where('product_id', $productId)
                    ->where('is_active', true)
                    ->whereHas('supplierApi', fn ($q) => $q->where('is_active', true)->where('supports_direct_top_up', true))
                    ->exists();

                if ($hasMapping) {
                    $needsDirectTopUp = true;
                    break;
                }
            }

            if ($needsDirectTopUp) {
                DirectTopUpFulfillmentJob::dispatch($order->id);
                Log::info('OrderObserver: dispatched DirectTopUpFulfillmentJob', [
                    'order_id' => $order->id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('OrderObserver: direct top-up dispatch failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "restored" event.
     */
    public function restored(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "force deleted" event.
     */
    public function forceDeleted(Order $order): void
    {
        //
    }
}
