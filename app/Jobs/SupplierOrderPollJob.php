<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\SupplierOrder;
use App\Services\DigitalProductCodeService;
use App\Services\Order\OrderFailureNoteFormatter;
use App\Services\Supplier\SupplierManager;
use App\Services\Supplier\SupplierOrderCodeProcessor;
use App\Services\Supplier\SupplierOrderEligibilityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Poll a supplier's GET order endpoint to fetch codes for an async order.
 *
 * Dispatched after BambooDriver::placeOrder() returns status='processing'
 * (V1 async flow where codes are not returned inline).
 * Replaces webhook dependency — polls until codes arrive or retries exhausted.
 */
class SupplierOrderPollJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 60;

    /**
     * @return int[]
     */
    public function backoff(): array
    {
        return [2, 4, 8, 15, 30, 60, 120, 180, 300];
    }

    public function __construct(
        public readonly int $supplierOrderId,
    ) {
        $this->onQueue('fulfillment');
        $this->afterCommit();
    }

    public function handle(
        SupplierManager $manager,
        DigitalProductCodeService $codeService,
        SupplierOrderCodeProcessor $codeProcessor,
        SupplierOrderEligibilityService $eligibilityService,
    ): void {
        $supplierOrder = SupplierOrder::with(['supplierApi', 'productMapping'])->find($this->supplierOrderId);

        if (! $supplierOrder) {
            Log::warning('SupplierOrderPollJob: SupplierOrder not found', [
                'supplier_order_id' => $this->supplierOrderId,
            ]);

            return;
        }

        if ($supplierOrder->order_id) {
            $linkedOrder = Order::find($supplierOrder->order_id);

            if ($linkedOrder && ! $eligibilityService->orderIsEligibleForAutomatedFulfillment($linkedOrder)) {
                Log::info('SupplierOrderPollJob: linked order is terminal, stopping poll', [
                    'supplier_order_id' => $supplierOrder->id,
                    'order_id' => $linkedOrder->id,
                    'order_status' => $linkedOrder->order_status,
                ]);

                if (! in_array($supplierOrder->status, ['fulfilled', 'failed', 'refunded'], true)) {
                    $supplierOrder->update([
                        'status' => 'failed',
                        'failed_reason' => 'Customer order is terminal — fulfillment will not be retried.',
                    ]);
                }

                return;
            }
        }

        // Already fulfilled — skip
        if (in_array($supplierOrder->status, ['fulfilled', 'failed', 'refunded'])) {
            Log::info('SupplierOrderPollJob: already resolved, skipping', [
                'id' => $supplierOrder->id,
                'status' => $supplierOrder->status,
            ]);

            return;
        }

        $supplier = $supplierOrder->supplierApi;
        $mapping = $supplierOrder->productMapping;

        if (! $supplier || ! $mapping) {
            Log::error('SupplierOrderPollJob: missing supplier or mapping', [
                'id' => $supplierOrder->id,
            ]);

            return;
        }

        try {
            $driver = $manager->driver($supplier);
            $result = $driver->getOrderStatus($supplierOrder->supplier_order_id);

            Log::info('SupplierOrderPollJob: polled supplier', [
                'id' => $supplierOrder->id,
                'supplier_order_id' => $supplierOrder->supplier_order_id,
                'status' => $result->status,
                'codes_count' => count($result->codes),
                'attempt' => $this->attempts(),
            ]);

            if ($result->status === 'failed') {
                $manager->completeDirectTopUpSupplierOrder(
                    supplierOrder: $supplierOrder,
                    status: 'failed',
                    rawResponse: $result->rawResponse,
                );

                return;
            }

            if ($mapping->is_direct_topup) {
                if ($result->status === 'fulfilled') {
                    $manager->completeDirectTopUpSupplierOrder(
                        supplierOrder: $supplierOrder,
                        status: 'fulfilled',
                        rawResponse: $result->rawResponse,
                    );

                    return;
                }

                if (in_array($result->status, ['processing', 'pending'], true)) {
                    $supplierOrder->update(['attempt_count' => $this->attempts()]);
                    $this->release($this->backoff()[$this->attempts() - 1] ?? 300);
                }

                return;
            }

            if ($result->hasCodes()) {
                $codeProcessor->processReceivedCodes($supplierOrder, $mapping, $result->codes);
                $codeProcessor->assignLinkedOrder($supplierOrder);

                return;
            }

            // Still processing — let the retry mechanism handle it
            if ($result->status === 'processing') {
                $supplierOrder->update(['attempt_count' => $this->attempts()]);
                $this->release($this->backoff()[$this->attempts() - 1] ?? 300);
            }
        } catch (\Throwable $e) {
            Log::error('SupplierOrderPollJob: poll failed', [
                'id' => $supplierOrder->id,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical('SupplierOrderPollJob: all retries exhausted', [
            'supplier_order_id' => $this->supplierOrderId,
            'error' => $exception->getMessage(),
        ]);

        $supplierOrder = SupplierOrder::with('productMapping')->find($this->supplierOrderId);

        if ($supplierOrder === null) {
            return;
        }

        $plainError = app(OrderFailureNoteFormatter::class)->fromThrowable($exception);

        $supplierOrder->update([
            'status' => 'failed',
            'failed_reason' => 'Poll retries exhausted: '.$plainError,
        ]);

        if ($supplierOrder->order_id && ($supplierOrder->productMapping?->is_direct_topup ?? false)) {
            $order = Order::find($supplierOrder->order_id);

            if ($order && app(SupplierOrderEligibilityService::class)->orderIsEligibleForAutomatedFulfillment($order)) {
                app(\App\Services\DirectTopUp\DirectTopUpWalletCheckoutService::class)->markDirectTopUpOrderFailed(
                    $order,
                    $plainError ?: translate('direct_topup_fulfillment_failed'),
                    (int) $order->customer_id,
                );
            }
        }
    }
}
