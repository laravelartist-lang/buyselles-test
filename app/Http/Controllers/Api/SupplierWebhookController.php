<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SupplierWebhookProcessJob;
use App\Models\SupplierApi;
use App\Services\Order\PendingReviewHoldService;
use App\Services\Supplier\SupplierManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class SupplierWebhookController extends Controller
{
    /**
     * Receive webhook from supplier.
     * POST /api/supplier/webhook/{supplier}
     *
     * Validates supplier exists, then dispatches async processing job.
     * Returns 200 immediately — processing is deferred to queue.
     */
    public function handle(Request $request, int $supplier): JsonResponse
    {
        $supplierApi = SupplierApi::find($supplier);

        if (! $supplierApi) {
            Log::warning('SupplierWebhookController: received webhook for unknown supplier', [
                'supplier_id' => $supplier,
            ]);

            return response()->json(['status' => 'ignored'], 200);
        }

        if (! $supplierApi->is_active) {
            Log::info('SupplierWebhookController: supplier inactive, ignoring webhook', [
                'supplier_id' => $supplier,
            ]);

            return response()->json(['status' => 'ignored'], 200);
        }

        SupplierWebhookProcessJob::dispatch(
            $supplierApi->id,
            $request->all(),
            $request->headers->all(),
            $request->fullUrl()
        );

        $this->holdFailedSupplierOrder($supplierApi, $request);

        return response()->json(['status' => 'received'], 200);
    }

    private function holdFailedSupplierOrder(SupplierApi $supplierApi, Request $request): void
    {
        try {
            $parsed = app(SupplierManager::class)->driver($supplierApi)->parseWebhook($request);

            if ($parsed->type !== 'order_failed' || $parsed->supplierOrderId === null) {
                return;
            }

            app(PendingReviewHoldService::class)->holdBySupplierOrderId(
                $parsed->supplierOrderId,
                PendingReviewHoldService::REASON_API_FAILED,
            );
        } catch (Throwable $exception) {
            Log::warning('SupplierWebhookController: could not hold failed supplier order', [
                'supplier_id' => $supplierApi->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
