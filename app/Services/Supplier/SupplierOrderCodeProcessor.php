<?php

namespace App\Services\Supplier;

use App\Models\DigitalProductCode;
use App\Models\Order;
use App\Models\SupplierOrder;
use App\Models\SupplierProductMapping;
use App\Services\DigitalProductCodeService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class SupplierOrderCodeProcessor
{
    public function __construct(
        private readonly DigitalProductCodeService $codeService,
    ) {}

    /**
     * @param  array<int, string|array{code: string, pin?: string|null, serial_number?: string|null, expiry_date?: string|null}>  $codes
     */
    public function processReceivedCodes(
        SupplierOrder $supplierOrder,
        SupplierProductMapping $mapping,
        array $codes,
    ): int {
        $plainCodes = array_map(
            fn ($code) => is_array($code) ? (string) ($code['code'] ?? '') : (string) $code,
            $codes,
        );
        $supplierOrder->setEncryptedCodes($plainCodes);

        $bulkResult = $this->codeService->bulkAddToPool(
            productId: $mapping->product_id,
            records: $codes,
            source: 'supplier_api',
        );

        $this->updateExistingCodeMetadata($codes, $mapping);

        $supplierOrder->update([
            'status' => $bulkResult['inserted'] >= $supplierOrder->quantity ? 'fulfilled' : 'partial',
            'fulfilled_at' => $bulkResult['inserted'] > 0 ? now() : null,
            'codes_received' => $supplierOrder->codes_received,
        ]);

        $mapping->update(['last_synced_at' => now()]);

        if ($bulkResult['inserted'] > 0) {
            $this->codeService->applyApiPriceIfManualDepleted($mapping->product_id);
        }

        Log::info('SupplierOrderCodeProcessor: codes processed', [
            'supplier_order_id' => $supplierOrder->id,
            'inserted' => $bulkResult['inserted'],
            'skipped' => $bulkResult['skipped'],
        ]);

        return $bulkResult['inserted'];
    }

    public function assignLinkedOrder(SupplierOrder $supplierOrder): void
    {
        if (! $supplierOrder->order_id) {
            return;
        }

        $order = Order::query()->find($supplierOrder->order_id);

        if ($order === null || $order->payment_status !== 'paid') {
            return;
        }

        $this->codeService->assignAndNotify($order);
    }

    /**
     * @param  array<int, string|array{code: string, pin?: string|null, serial_number?: string|null, expiry_date?: string|null}>  $codes
     */
    private function updateExistingCodeMetadata(array $codes, SupplierProductMapping $mapping): void
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

            $hash = hash('sha256', strtolower(trim($plainCode)));
            $existing = DigitalProductCode::query()
                ->where('code_hash', $hash)
                ->where('product_id', $mapping->product_id)
                ->first();

            if ($existing === null) {
                continue;
            }

            $updateData = [];

            if ($existing->pin === null && $pin !== null && $pin !== '') {
                $updateData['pin'] = Crypt::encryptString($pin);
            }

            if (($existing->serial_number === null || $existing->serial_number === '') && $serialNumber !== null && $serialNumber !== '') {
                $updateData['serial_number'] = $serialNumber;
            }

            if ($existing->expiry_date === null && $expiryDate !== null) {
                $updateData['expiry_date'] = $expiryDate;
            }

            if ($updateData !== []) {
                DigitalProductCode::query()->where('id', $existing->id)->update($updateData);
            }
        }
    }
}
