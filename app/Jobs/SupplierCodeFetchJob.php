<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\SupplierOrder;
use App\Services\Supplier\SupplierFulfillmentFailureService;
use App\Services\Supplier\SupplierManager;
use App\Services\Supplier\SupplierOrderEligibilityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * On-demand code fetch job — dispatched when local stock = 0 and product has supplier mappings.
 *
 * Retry strategy: 3 attempts with exponential backoff (30s / 60s / 120s).
 * Flow: fetch codes from supplier → add to pool → re-run assignAndNotify → deliver to customer.
 */
class SupplierCodeFetchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var int[] */
    public array $backoff = [30, 60, 120];

    public function __construct(
        public readonly int $orderId,
    ) {
        $this->onQueue('fulfillment');
        $this->afterCommit();
    }

    public function handle(
        SupplierManager $manager,
        SupplierFulfillmentFailureService $failureService,
        SupplierOrderEligibilityService $eligibilityService,
    ): void {
        $order = Order::find($this->orderId);

        if (! $order) {
            Log::warning('SupplierCodeFetchJob: order not found', ['order_id' => $this->orderId]);

            return;
        }

        if (! $eligibilityService->orderIsEligibleForAutomatedFulfillment($order)) {
            Log::info('SupplierCodeFetchJob: terminal order status, skipping', [
                'order_id' => $this->orderId,
                'order_status' => $order->order_status,
            ]);

            return;
        }

        if ($order->payment_status !== 'paid') {
            Log::info('SupplierCodeFetchJob: order not paid, skipping', ['order_id' => $this->orderId]);

            return;
        }

        try {
            $result = $manager->fulfillOrder($order);

            Log::info('SupplierCodeFetchJob: completed', [
                'order_id' => $this->orderId,
                'fulfilled' => $result['fulfilled'],
                'error' => $result['error'],
            ]);

            if (! $result['fulfilled'] && $result['error']) {
                $hasAsyncSupplierOrder = SupplierOrder::query()
                    ->where('order_id', $order->id)
                    ->whereIn('status', ['processing', 'pending', 'fulfilled'])
                    ->exists();

                if ($hasAsyncSupplierOrder) {
                    Log::info('SupplierCodeFetchJob: async supplier order in progress, not marking order failed', [
                        'order_id' => $this->orderId,
                    ]);

                    return;
                }

                $failureService->markOrderFailed(
                    $order->fresh(),
                    $result['error'],
                    (int) $order->customer_id
                );

                Log::error('SupplierCodeFetchJob: fulfillment failed', [
                    'order_id' => $this->orderId,
                    'error' => $result['error'],
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('SupplierCodeFetchJob: failed', [
                'order_id' => $this->orderId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure after all retries exhausted.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::critical('SupplierCodeFetchJob: all retries exhausted', [
            'order_id' => $this->orderId,
            'error' => $exception?->getMessage(),
        ]);

        $order = Order::find($this->orderId);

        if ($order === null) {
            return;
        }

        if (app(SupplierOrderEligibilityService::class)->orderHasTerminalFulfillmentStatus($order)) {
            return;
        }

        app(SupplierFulfillmentFailureService::class)->markOrderFailed(
            $order,
            $exception?->getMessage() ?: 'Supplier fulfillment failed after retries.',
            (int) $order->customer_id
        );
    }
}
