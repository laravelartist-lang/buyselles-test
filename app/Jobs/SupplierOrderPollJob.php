<?php

namespace App\Jobs;

use App\Models\DigitalProductCode;
use App\Models\Order;
use App\Models\SupplierOrder;
use App\Services\DigitalProductCodeService;
use App\Services\Supplier\SupplierManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
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
    }

    public function handle(SupplierManager $manager, DigitalProductCodeService $codeService): void
    {
        $supplierOrder = SupplierOrder::with(['supplierApi', 'productMapping'])->find($this->supplierOrderId);

        if (! $supplierOrder) {
            Log::warning('SupplierOrderPollJob: SupplierOrder not found', [
                'supplier_order_id' => $this->supplierOrderId,
            ]);

            return;
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
                $this->processAndAssign($supplierOrder, $mapping, $result->codes, $codeService, $manager);

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

    private function processAndAssign(
        SupplierOrder $supplierOrder,
        \App\Models\SupplierProductMapping $mapping,
        array $codes,
        DigitalProductCodeService $codeService,
        SupplierManager $manager,
    ): void {
        // Encrypt codes into SupplierOrder
        $plainCodes = array_map(fn ($c) => is_array($c) ? ($c['code'] ?? '') : $c, $codes);
        $supplierOrder->setEncryptedCodes($plainCodes);

        // Add codes to pool via code service (inserts new, skips duplicates)
        $bulkResult = $codeService->bulkAddToPool(
            productId: $mapping->product_id,
            records: $codes,
            source: 'supplier_api',
        );

        // Update existing codes with richer metadata when the API returns pin/serial/expiry
        // after the initial poll (Bamboo may populate card metadata asynchronously).
        $this->updateExistingCodeMetadata($codes, $mapping);

        $supplierOrder->update([
            'status' => $bulkResult['inserted'] >= $supplierOrder->quantity ? 'fulfilled' : 'partial',
            'fulfilled_at' => $bulkResult['inserted'] > 0 ? now() : null,
            'codes_received' => $supplierOrder->codes_received,
            'attempt_count' => $this->attempts(),
        ]);

        $mapping->update(['last_synced_at' => now()]);

        if ($bulkResult['inserted'] > 0) {
            $codeService->applyApiPriceIfManualDepleted($mapping->product_id);
        }

        Log::info('SupplierOrderPollJob: codes processed', [
            'id' => $supplierOrder->id,
            'inserted' => $bulkResult['inserted'],
            'skipped' => $bulkResult['skipped'],
        ]);

        // If linked to a platform order, assign codes and send email
        if ($supplierOrder->order_id) {
            $order = Order::find($supplierOrder->order_id);
            if ($order && $order->payment_status === 'paid') {
                $codeService->assignAndNotify($order);

                Log::info('SupplierOrderPollJob: codes assigned and customer notified', [
                    'supplier_order_id' => $supplierOrder->id,
                    'order_id' => $order->id,
                ]);
            }
        }
    }

    /**
     * Update existing DigitalProductCode records with richer metadata (pin, serial, expiry)
     * when the supplier API returns data that wasn't available on an earlier poll.
     *
     * Bamboo may initially return card codes without pin/serial/expiry, then populate
     * those fields asynchronously. This method fills in the gaps on subsequent polls.
     *
     * @param  array<int, string|array{code: string, pin?: string|null, serial_number?: string|null, expiry_date?: string|null}>  $codes
     * @param  \App\Models\SupplierProductMapping  $mapping  The product mapping (used for product_id scope)
     */
    private function updateExistingCodeMetadata(array $codes, \App\Models\SupplierProductMapping $mapping): void
    {
        foreach ($codes as $record) {
            if (is_string($record)) {
                continue;
            }

            $plainCode = trim((string) ($record['code'] ?? ''));
            $pin = isset($record['pin']) ? trim((string) $record['pin']) : null;
            $serialNumber = isset($record['serial_number']) ? trim((string) $record['serial_number']) : null;
            $expiryDate = $record['expiry_date'] ?? null;

            if ($plainCode === '') {
                continue;
            }

            // Find existing code by hash (scoped to product to avoid cross-seller collisions)
            $hash = hash('sha256', strtolower(trim($plainCode)));
            $existing = DigitalProductCode::where('code_hash', $hash)
                ->where('product_id', $mapping->product_id)
                ->first();

            if (! $existing) {
                continue;
            }

            $needsUpdate = false;
            $updateData = [];

            // Update pin if the existing record is missing it
            if ($existing->pin === null && $pin !== null && $pin !== '') {
                $updateData['pin'] = Crypt::encryptString($pin);
                $needsUpdate = true;
            }

            // Update serial_number if missing
            if (($existing->serial_number === null || $existing->serial_number === '') && $serialNumber !== null && $serialNumber !== '') {
                $updateData['serial_number'] = $serialNumber;
                $needsUpdate = true;
            }

            // Update expiry_date if missing
            if ($existing->expiry_date === null && $expiryDate !== null) {
                $updateData['expiry_date'] = $expiryDate;
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                DigitalProductCode::where('id', $existing->id)->update($updateData);

                Log::info('SupplierOrderPollJob: updated existing code metadata', [
                    'code_id' => $existing->id,
                    'code_hash' => $hash,
                    'updated_fields' => array_keys($updateData),
                ]);
            }
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

        $supplierOrder->update([
            'status' => 'failed',
            'failed_reason' => 'Poll retries exhausted: '.$exception->getMessage(),
        ]);

        if ($supplierOrder->order_id && ($supplierOrder->productMapping?->is_direct_topup ?? false)) {
            $order = Order::find($supplierOrder->order_id);

            if ($order) {
                app(\App\Services\DirectTopUp\DirectTopUpWalletCheckoutService::class)->markDirectTopUpOrderFailed(
                    $order,
                    $exception->getMessage() ?: translate('direct_topup_fulfillment_failed'),
                    (int) $order->customer_id,
                );
            }
        }
    }
}
