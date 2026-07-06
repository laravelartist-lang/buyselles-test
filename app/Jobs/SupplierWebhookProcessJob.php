<?php

namespace App\Jobs;

use App\Models\SupplierApi;
use App\Services\Supplier\SupplierManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Processes supplier webhooks asynchronously.
 *
 * Dispatched from SupplierWebhookController to prevent slow supplier callbacks
 * from blocking HTTP responses.
 */
class SupplierWebhookProcessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var int[] */
    public array $backoff = [10, 30, 60];

    /**
     * @param  int  $supplierApiId  The supplier that sent the webhook.
     * @param  array<string, mixed>  $payload  The raw webhook payload.
     * @param  array<string, string>  $headers  Relevant webhook headers (signature, content-type).
     * @param  string  $fullUrl  The full URL the webhook was sent to.
     */
    public function __construct(
        public readonly int $supplierApiId,
        public readonly array $payload,
        public readonly array $headers,
        public readonly string $fullUrl,
    ) {
        $this->onQueue('fulfillment');
    }

    public function handle(SupplierManager $manager): void
    {
        $supplier = SupplierApi::find($this->supplierApiId);

        if (! $supplier) {
            Log::warning('SupplierWebhookProcessJob: supplier not found', [
                'supplier_api_id' => $this->supplierApiId,
            ]);

            return;
        }

        // Reconstruct a request-like object for the driver's parseWebhook method.
        // Pass the payload as a raw JSON body so that when Content-Type: application/json
        // is present, $request->all() correctly decodes it instead of trying to parse
        // form-encoded data as JSON (which returns an empty array).
        $request = Request::create(
            $this->fullUrl,
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($this->payload),
        );
        foreach ($this->headers as $key => $value) {
            $request->headers->set($key, $value);
        }

        try {
            $manager->handleWebhook($supplier, $request);

            Log::info('SupplierWebhookProcessJob: processed', [
                'supplier_id' => $this->supplierApiId,
            ]);
        } catch (\Throwable $e) {
            Log::error('SupplierWebhookProcessJob: failed', [
                'supplier_id' => $this->supplierApiId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::critical('SupplierWebhookProcessJob: all retries exhausted', [
            'supplier_id' => $this->supplierApiId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
