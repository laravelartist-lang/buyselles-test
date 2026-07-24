<?php

namespace App\Services\Partner;

use App\Jobs\SupplierOrderPollJob;
use App\Models\Order;
use App\Models\Product;
use App\Models\SupplierOrder;
use App\Models\SupplierProductMapping;
use App\Services\DigitalProductCodeService;
use App\Services\Order\OrderFailureNoteFormatter;
use App\Services\Supplier\SupplierManager;
use App\Services\Supplier\SupplierOrderCodeProcessor;
use App\Services\Supplier\SupplierOrderEligibilityService;
use Illuminate\Support\Facades\Log;

class PartnerSupplierFulfillmentService
{
    public function __construct(
        private readonly SupplierManager $supplierManager,
        private readonly SupplierOrderEligibilityService $eligibilityService,
        private readonly SupplierOrderCodeProcessor $codeProcessor,
        private readonly DigitalProductCodeService $codeService,
        private readonly OrderFailureNoteFormatter $failureNoteFormatter,
    ) {}

    /**
     * @return array{
     *     success: bool,
     *     pending: bool,
     *     failed: bool,
     *     error: ?string,
     *     supplier_order_id: ?string,
     *     supplier_request_id: ?string
     * }
     */
    public function fulfillSynchronously(Order $order): array
    {
        $order->loadMissing('orderDetails');

        $supplierOrderId = null;
        $supplierRequestId = null;

        foreach ($order->orderDetails as $detail) {
            if (! $this->eligibilityService->orderDetailNeedsSupplierCodeFetch($detail)) {
                continue;
            }

            $productId = (int) $detail->product_id;
            $product = Product::query()
                ->withoutGlobalScope(Product::STOREFRONT_SCOPE)
                ->find($productId);

            if ($product === null) {
                continue;
            }

            $mapping = $this->resolveCodeMapping($productId);

            if ($mapping === null || ! $mapping->isSupplierFirst()) {
                continue;
            }

            $alreadyAssigned = \App\Models\DigitalProductCode::query()
                ->where('order_detail_id', $detail->id)
                ->where('status', 'sold')
                ->count();

            $needed = max(0, (int) $detail->qty - $alreadyAssigned);

            if ($needed <= 0) {
                continue;
            }

            try {
                $result = $this->supplierManager->fetchAndStockCodes(
                    product: $product,
                    quantity: $needed,
                    customAmount: $detail->custom_amount !== null ? (float) $detail->custom_amount : null,
                    denominationId: $detail->supplier_denomination_id,
                );

                if ($result['supplier_order_id']) {
                    SupplierOrder::query()->where('id', $result['supplier_order_id'])->update([
                        'order_id' => $order->id,
                        'order_detail_id' => $detail->id,
                    ]);

                    $supplierOrder = SupplierOrder::query()->find($result['supplier_order_id']);
                    $supplierOrderId = (string) $supplierOrder?->id;
                    $supplierRequestId = (string) ($supplierOrder?->supplier_order_id ?? '');

                    if (($result['inserted'] ?? 0) > 0) {
                        $this->codeService->assignAndNotify($order);

                        continue;
                    }

                    if ($supplierOrder !== null && $this->pollUntilCodes($supplierOrder)) {
                        $this->codeService->assignAndNotify($order);

                        continue;
                    }

                    if ($supplierOrder !== null) {
                        SupplierOrderPollJob::dispatch($supplierOrder->id)
                            ->delay(now()->addSeconds(30));

                        return [
                            'success' => false,
                            'pending' => true,
                            'failed' => false,
                            'error' => null,
                            'supplier_order_id' => $supplierOrderId,
                            'supplier_request_id' => $supplierRequestId,
                        ];
                    }
                }

                if (isset($result['error'])) {
                    return [
                        'success' => false,
                        'pending' => false,
                        'failed' => true,
                        'error' => (string) $result['error'],
                        'supplier_order_id' => $supplierOrderId,
                        'supplier_request_id' => $supplierRequestId,
                    ];
                }
            } catch (\Throwable $e) {
                Log::error('PartnerSupplierFulfillmentService: sync fulfillment failed', [
                    'order_id' => $order->id,
                    'product_id' => $productId,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'pending' => false,
                    'failed' => true,
                    'error' => $this->failureNoteFormatter->fromThrowable($e),
                    'supplier_order_id' => $supplierOrderId,
                    'supplier_request_id' => $supplierRequestId,
                ];
            }
        }

        $this->codeService->assignAndNotify($order);

        return [
            'success' => true,
            'pending' => false,
            'failed' => false,
            'error' => null,
            'supplier_order_id' => $supplierOrderId,
            'supplier_request_id' => $supplierRequestId,
        ];
    }

    private function pollUntilCodes(SupplierOrder $supplierOrder): bool
    {
        $supplierOrder->loadMissing(['supplierApi', 'productMapping']);

        $supplier = $supplierOrder->supplierApi;
        $mapping = $supplierOrder->productMapping;

        if ($supplier === null || $mapping === null) {
            return false;
        }

        $timeoutSeconds = (int) config('partner.sync_fulfillment_timeout', 60);
        $intervalMs = max(500, (int) config('partner.sync_poll_interval_ms', 500));
        $deadline = microtime(true) + $timeoutSeconds;

        while (microtime(true) < $deadline) {
            try {
                $driver = $this->supplierManager->driver($supplier);
                $result = $driver->getOrderStatus($supplierOrder->supplier_order_id);

                if ($result->status === 'failed') {
                    $supplierOrder->update([
                        'status' => 'failed',
                        'failed_reason' => 'Supplier order failed during sync poll.',
                    ]);

                    return false;
                }

                if ($result->hasCodes()) {
                    $this->codeProcessor->processReceivedCodes($supplierOrder, $mapping, $result->codes);

                    return true;
                }
            } catch (\Throwable $e) {
                Log::warning('PartnerSupplierFulfillmentService: poll attempt failed', [
                    'supplier_order_id' => $supplierOrder->id,
                    'error' => $e->getMessage(),
                ]);
            }

            if ((microtime(true) + ($intervalMs / 1000)) >= $deadline) {
                break;
            }

            usleep($intervalMs * 1000);
        }

        return false;
    }

    private function resolveCodeMapping(int $productId): ?SupplierProductMapping
    {
        return SupplierProductMapping::query()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->where('is_direct_topup', false)
            ->whereHas('supplierApi', fn ($query) => $query->where('is_active', true))
            ->orderBy('priority')
            ->with('supplierApi')
            ->first();
    }
}
