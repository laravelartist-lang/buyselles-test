<?php

namespace App\Jobs;

use App\Models\Order;
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
    ) {}

    public function handle(SupplierManager $manager): void
    {
        $order = Order::find($this->orderId);

        if (! $order) {
            Log::warning('DirectTopUpFulfillmentJob: order not found', ['order_id' => $this->orderId]);

            return;
        }

        if ($order->payment_status !== 'paid') {
            Log::info('DirectTopUpFulfillmentJob: order not paid, skipping', ['order_id' => $this->orderId]);

            return;
        }

        try {
            $fulfilled = $manager->fulfillDirectTopUpOrder($order);

            Log::info('DirectTopUpFulfillmentJob: completed', [
                'order_id' => $this->orderId,
                'fulfilled' => $fulfilled,
            ]);
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
    }
}
