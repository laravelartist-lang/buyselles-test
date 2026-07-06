<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\DirectTopUp\DirectTopUpWalletCheckoutService;
use App\Services\Supplier\SupplierManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DirectTopUpFulfillmentJob implements ShouldQueue
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
    }

    public function handle(
        SupplierManager $manager,
        DirectTopUpWalletCheckoutService $walletCheckoutService,
    ): void {
        $order = Order::find($this->orderId);

        if (! $order) {
            Log::warning('DirectTopUpFulfillmentJob: order not found', ['order_id' => $this->orderId]);

            return;
        }

        if ($order->payment_status !== 'paid') {
            Log::info('DirectTopUpFulfillmentJob: order not paid, skipping', ['order_id' => $this->orderId]);

            return;
        }

        if (DirectTopUpWalletCheckoutService::isDirectTopUpAlreadyFulfilled($order)) {
            Log::info('DirectTopUpFulfillmentJob: already fulfilled, skipping', ['order_id' => $this->orderId]);

            return;
        }

        try {
            $result = $manager->fulfillDirectTopUpOrder($order);

            Log::info('DirectTopUpFulfillmentJob: completed', [
                'order_id' => $this->orderId,
                'fulfilled' => $result['fulfilled'],
                'error' => $result['error'],
            ]);

            if ($result['fulfilled']) {
                $walletCheckoutService->markDirectTopUpOrderDelivered(
                    $order->fresh(),
                    (int) $order->customer_id
                );

                return;
            }

            if ($result['error']) {
                $walletCheckoutService->markDirectTopUpOrderFailed(
                    $order->fresh(),
                    $walletCheckoutService->formatFulfillmentError($result['error']),
                    (int) $order->customer_id
                );

                Log::error('DirectTopUpFulfillmentJob: fulfillment failed', [
                    'order_id' => $this->orderId,
                    'error' => $result['error'],
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('DirectTopUpFulfillmentJob: failed', [
                'order_id' => $this->orderId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::critical('DirectTopUpFulfillmentJob: all retries exhausted', [
            'order_id' => $this->orderId,
            'error' => $exception?->getMessage(),
        ]);

        $order = Order::find($this->orderId);

        if ($order === null) {
            return;
        }

        app(DirectTopUpWalletCheckoutService::class)->markDirectTopUpOrderFailed(
            $order,
            $exception?->getMessage() ?: translate('direct_topup_fulfillment_failed'),
            (int) $order->customer_id
        );
    }
}
