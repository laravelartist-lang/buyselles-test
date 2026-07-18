<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\Supplier\MappedProductFulfillmentService;
use App\Traits\PushNotificationTrait;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    use PushNotificationTrait;

    public function __construct(
        private readonly MappedProductFulfillmentService $mappedProductFulfillment,
    ) {}

    /**
     * Handle the Order "created" event.
     * Some payment methods (wallet, free orders) create the order already in 'paid' status,
     * so the updated observer won't fire. We handle those here.
     */
    public function created(Order $order): void
    {
        if ($order->payment_status === 'paid') {
            try {
                $this->mappedProductFulfillment->fulfillStorefrontOrder($order);
            } catch (\Throwable $e) {
                Log::error('OrderObserver: digital fulfillment failed on create', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
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
                $this->mappedProductFulfillment->fulfillStorefrontOrder($order);
            } catch (\Throwable $e) {
                Log::error('OrderObserver: digital fulfillment failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
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
