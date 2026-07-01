<?php

namespace App\Services\Supplier;

use App\Contracts\SupplierDriverInterface;
use App\DTOs\Supplier\BalanceResult;
use App\Jobs\SupplierOrderPollJob;
use App\Models\DigitalProductCode;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\SupplierApi;
use App\Models\SupplierOrder;
use App\Models\SupplierProductDenomination;
use App\Models\SupplierProductMapping;
use App\Services\DigitalProductCodeService;
use App\Services\DirectTopUp\DirectTopUpService;
use App\Services\Supplier\Drivers\BambooDriver;
use App\Services\Supplier\Drivers\GenericRestDriver;
use App\Services\Supplier\Drivers\KinguinDriver;
use App\Services\Supplier\Drivers\ReloadlyDriver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SupplierManager
{
    /**
     * Map of driver keys to their implementing classes.
     * Adding a new supplier = add one entry here + one driver class.
     *
     * @var array<string, class-string<SupplierDriverInterface>>
     */
    protected array $drivers = [
        'generic_rest' => GenericRestDriver::class,
        'reloadly' => ReloadlyDriver::class,
        'kinguin' => KinguinDriver::class,
        'bamboo' => BambooDriver::class,
    ];

    public function __construct(
        private readonly DigitalProductCodeService $codeService,
        private readonly SupplierApiLogger $logger,
        private readonly SupplierRateLimiter $rateLimiter,
    ) {}

    /**
     * Resolve and configure a driver instance for the given supplier.
     */
    public function driver(SupplierApi $supplier): SupplierDriverInterface
    {
        $driverClass = $this->drivers[$supplier->driver] ?? null;

        if (! $driverClass) {
            throw new \InvalidArgumentException("Unknown supplier driver: {$supplier->driver}");
        }

        /** @var SupplierDriverInterface $instance */
        $instance = app($driverClass);

        return $instance->configure($supplier);
    }

    /**
     * Get all registered driver keys.
     *
     * @return string[]
     */
    public function getAvailableDrivers(): array
    {
        return array_keys($this->drivers);
    }

    /**
     * Fetch codes from suppliers and add them to the digital code pool.
     * Tries suppliers in priority order (fallback chain).
     *
     * @param  float|null  $customAmount  Customer-chosen face value for customizable/variable products
     * @param  int|null  $denominationId  Selected denomination ID (for fixed/variable denomination products)
     * @return array{inserted: int, supplier_id: int|null, supplier_order_id: int|null}
     */
    public function fetchAndStockCodes(Product $product, int $quantity, ?float $customAmount = null, ?int $denominationId = null): array
    {
        $denomination = $denominationId
            ? SupplierProductDenomination::with('mapping.supplierApi')->find($denominationId)
            : null;

        $mappings = SupplierProductMapping::where('product_id', $product->id)
            ->active()
            ->byPriority()
            ->with('supplierApi')
            ->get();

        foreach ($mappings as $mapping) {
            $supplier = $mapping->supplierApi;

            if (! $supplier->is_active || $supplier->isDown()) {
                continue;
            }

            if (! $this->rateLimiter->attempt($supplier->id, $supplier->rate_limit_per_minute)) {
                Log::warning('SupplierManager: rate limited', [
                    'supplier_id' => $supplier->id,
                    'product_id' => $product->id,
                ]);

                continue;
            }

            try {
                $result = $this->placeSupplierOrder($supplier, $mapping, $quantity, $customAmount, $denomination);

                if ($result['inserted'] > 0 || $result['supplier_order_id']) {
                    return $result;
                }
            } catch (\Throwable $e) {
                Log::error('SupplierManager: fetchAndStockCodes failed for supplier', [
                    'supplier_id' => $supplier->id,
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['inserted' => 0, 'supplier_id' => null, 'supplier_order_id' => null];
    }

    /**
     * Fulfill an order by fetching codes from suppliers on-demand.
     * Called when local pool is exhausted and product has supplier mappings.
     *
     * @return bool True if codes were obtained and assigned
     */
    public function fulfillOrder(Order $order): bool
    {
        $order->loadMissing('orderDetails');

        $anyFulfilled = false;

        foreach ($order->orderDetails as $detail) {
            $productDetails = json_decode($detail->product_details ?? '{}');
            $productType = $productDetails->product_type ?? null;
            $digitalType = $productDetails->digital_product_type ?? null;
            $isDirectTopUp = (bool) ($productDetails->is_direct_topup ?? false);

            if ($isDirectTopUp) {
                continue;
            }

            if ($productType !== 'digital' || $digitalType !== 'ready_product') {
                continue;
            }

            $productId = $detail->product_id ?? ($productDetails->id ?? null);
            if (! $productId) {
                continue;
            }

            // Check how many codes are still needed
            $alreadyAssigned = DigitalProductCode::where('order_detail_id', $detail->id)
                ->where('status', 'sold')
                ->count();

            $needed = max(0, (int) $detail->qty - $alreadyAssigned);

            if ($needed <= 0) {
                continue;
            }

            // Check if product has supplier mappings
            $hasSuppliers = SupplierProductMapping::where('product_id', $productId)
                ->active()
                ->exists();

            if (! $hasSuppliers) {
                continue;
            }

            $product = Product::find($productId);
            if (! $product) {
                continue;
            }

            $result = $this->fetchAndStockCodes($product, $needed, $detail->custom_amount, $detail->supplier_denomination_id);

            // Always link supplier order to platform order (even for async/V1 where codes come via webhook)
            if ($result['supplier_order_id']) {
                SupplierOrder::where('id', $result['supplier_order_id'])->update([
                    'order_id' => $order->id,
                    'order_detail_id' => $detail->id,
                ]);
            }

            if ($result['inserted'] > 0) {
                $anyFulfilled = true;
            } elseif ($result['supplier_order_id']) {
                // Async supplier (e.g. Bamboo V1) — codes will arrive later.
                // Dispatch a poll job to fetch codes from the supplier's GET order endpoint.
                SupplierOrderPollJob::dispatch($result['supplier_order_id'])
                    ->delay(now()->addSeconds(30));

                Log::info('SupplierManager: dispatched poll job for async supplier order', [
                    'supplier_order_id' => $result['supplier_order_id'],
                    'order_id' => $order->id,
                    'product_id' => $productId,
                ]);
            }
        }

        if ($anyFulfilled) {
            // Re-run assignment now that new codes are in the pool
            $this->codeService->assignAndNotify($order);
        }

        return $anyFulfilled;
    }

    /**
     * Sync stock for a specific product-supplier mapping.
     * Checks remote stock, auto-restocks if below threshold.
     */
    public function syncStock(SupplierProductMapping $mapping): void
    {
        $supplier = $mapping->supplierApi;

        if (! $supplier->is_active || $supplier->isDown()) {
            return;
        }

        if (! $this->rateLimiter->attempt($supplier->id, $supplier->rate_limit_per_minute)) {
            return;
        }

        $logId = $this->logger->logRequest(
            supplierApiId: $supplier->id,
            action: 'fetch_stock',
            endpoint: $supplier->base_url,
            method: 'GET',
        );

        $startTime = microtime(true);

        try {
            $driver = $this->driver($supplier);
            $stockResult = $driver->fetchStock($mapping->supplier_product_id);

            $this->logger->logResponse(
                logId: $logId,
                httpStatusCode: 200,
                responsePayload: [
                    'available' => $stockResult->available,
                    'price' => $stockResult->price,
                    'currency' => $stockResult->currency,
                ],
                responseTimeMs: (int) ((microtime(true) - $startTime) * 1000),
            );

            // Update cost price from supplier if needed
            if ($stockResult->price > 0 && $stockResult->price != $mapping->cost_price) {
                $mapping->update([
                    'cost_price' => $stockResult->price,
                    'cost_currency' => $stockResult->currency,
                ]);
            }

            // Always sync the product's selling price when manual stock is depleted
            $this->codeService->applyApiPriceIfManualDepleted($mapping->product_id);

            $mapping->update(['last_synced_at' => now()]);

            // Auto-restock if below threshold
            if ($mapping->auto_restock) {
                $localStock = DigitalProductCode::where('product_id', $mapping->product_id)
                    ->available()
                    ->count();

                if ($localStock < $mapping->min_stock_threshold && $stockResult->available > 0) {
                    $qty = min($mapping->max_restock_qty, $stockResult->available);
                    $this->placeSupplierOrder($supplier, $mapping, $qty);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->logError(
                logId: $logId,
                errorMessage: $e->getMessage(),
                responseTimeMs: (int) ((microtime(true) - $startTime) * 1000),
            );
        }
    }

    /**
     * Process an incoming webhook from a supplier.
     */
    public function handleWebhook(SupplierApi $supplier, \Illuminate\Http\Request $request): void
    {
        $logId = $this->logger->logRequest(
            supplierApiId: $supplier->id,
            action: 'webhook',
            endpoint: $request->fullUrl(),
            method: $request->method(),
            requestPayload: $request->all(),
        );

        $startTime = microtime(true);

        try {
            $driver = $this->driver($supplier);
            $result = $driver->parseWebhook($request);

            if (! $result->isVerified()) {
                $this->logger->logError($logId, 'Webhook signature verification failed');

                return;
            }

            $this->logger->logResponse(
                logId: $logId,
                httpStatusCode: 200,
                responsePayload: ['type' => $result->type, 'status' => $result->status, 'codes_count' => count($result->codes)],
                responseTimeMs: (int) ((microtime(true) - $startTime) * 1000),
            );

            // Process codes from webhook
            if ($result->supplierOrderId) {
                $supplierOrder = SupplierOrder::where('supplier_api_id', $supplier->id)
                    ->where('supplier_order_id', $result->supplierOrderId)
                    ->first();

                if ($supplierOrder) {
                    if ($result->hasCodes()) {
                        // ── Replace any existing codes for this order ─────────────
                        // The Bamboo GET order endpoint returns a fresh pool each time,
                        // so codes saved by SupplierOrderPollJob may be incorrect.
                        // The webhook delivers the ACTUAL historical codes, so we
                        // replace any previously assigned codes with the real ones.
                        if ($supplier->driver === 'bamboo' && $supplierOrder->order_id) {
                            $this->replaceOrderCodes(
                                orderId: $supplierOrder->order_id,
                                supplierOrder: $supplierOrder,
                                mapping: $supplierOrder->productMapping,
                                realCodes: $result->codes,
                            );
                        } else {
                            $this->processReceivedCodes(
                                supplierOrder: $supplierOrder,
                                mapping: $supplierOrder->productMapping,
                                codes: $result->codes,
                            );
                        }

                        // If this supplier order is linked to a platform order, assign codes to customer
                        if ($supplierOrder->order_id) {
                            $order = Order::find($supplierOrder->order_id);
                            if ($order) {
                                $this->codeService->assignAndNotify($order);
                            }
                        }
                    } elseif ($result->type === 'order_failed') {
                        $supplierOrder->update(['status' => 'failed']);
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->logger->logError(
                logId: $logId,
                errorMessage: $e->getMessage(),
                responseTimeMs: (int) ((microtime(true) - $startTime) * 1000),
            );
        }
    }

    /**
     * Place an order with a supplier and process received codes.
     *
     * @param  float|null  $customAmount  Customer-chosen face value for customizable/variable products
     * @param  SupplierProductDenomination|null  $denomination  Selected denomination (overrides mapping product ID)
     * @return array{inserted: int, supplier_id: int, supplier_order_id: int|null}
     */
    private function placeSupplierOrder(
        SupplierApi $supplier,
        SupplierProductMapping $mapping,
        int $quantity,
        ?float $customAmount = null,
        ?SupplierProductDenomination $denomination = null,
    ): array {
        // When a denomination is selected, use its supplier_product_id instead of the mapping's
        $supplierProductId = $denomination?->supplier_product_id ?? $mapping->supplier_product_id;

        $logId = $this->logger->logRequest(
            supplierApiId: $supplier->id,
            action: 'place_order',
            endpoint: $supplier->base_url,
            method: 'POST',
            requestPayload: [
                'supplier_product_id' => $supplierProductId,
                'quantity' => $quantity,
                'denomination_id' => $denomination?->id,
            ],
        );

        $startTime = microtime(true);

        try {
            $driver = $this->driver($supplier);

            // For fixed denominations: use face_value. For variable: use customAmount. Fallback: mapping cost.
            if ($denomination?->isFixed()) {
                $unitPrice = (float) $denomination->face_value;
            } elseif ($denomination?->isVariable() && $customAmount) {
                $unitPrice = $customAmount;
            } else {
                $unitPrice = $customAmount ?? (float) $mapping->cost_price;
            }

            $result = $driver->placeOrder($supplierProductId, $quantity, $unitPrice);

            $this->logger->logResponse(
                logId: $logId,
                httpStatusCode: 200,
                responsePayload: [
                    'supplier_order_id' => $result->supplierOrderId,
                    'status' => $result->status,
                    'codes_count' => count($result->codes),
                ],
                responseTimeMs: (int) ((microtime(true) - $startTime) * 1000),
            );

            // Create supplier order record
            $costPerUnit = $denomination?->cost_price ?? $customAmount ?? $mapping->cost_price;
            $supplierOrder = SupplierOrder::create([
                'supplier_api_id' => $supplier->id,
                'supplier_product_mapping_id' => $mapping->id,
                'supplier_order_id' => $result->supplierOrderId,
                'quantity' => $quantity,
                'cost_per_unit' => $costPerUnit,
                'total_cost' => $costPerUnit * $quantity,
                'cost_currency' => $denomination?->cost_currency ?? $mapping->cost_currency,
                'status' => $result->status,
            ]);

            $inserted = 0;

            if ($result->hasCodes()) {
                $inserted = $this->processReceivedCodes($supplierOrder, $mapping, $result->codes);
            }

            return [
                'inserted' => $inserted,
                'supplier_id' => $supplier->id,
                'supplier_order_id' => $supplierOrder->id,
            ];
        } catch (\Throwable $e) {
            $this->logger->logError(
                logId: $logId,
                errorMessage: $e->getMessage(),
                responseTimeMs: (int) ((microtime(true) - $startTime) * 1000),
            );

            throw $e;
        }
    }

    /**
     * Fetch balance/credit from all active suppliers. Cached for 15 minutes.
     *
     * @return array<int, array{id: int, name: string, driver: string, balance: BalanceResult}>
     */
    public function getSupplierBalances(): array
    {
        return Cache::remember('supplier_api_balances', 900, function () {
            $suppliers = SupplierApi::where('is_active', true)->get();

            return $suppliers->map(function (SupplierApi $supplier) {
                try {
                    $balance = $this->driver($supplier)->getBalance();
                } catch (\Throwable) {
                    $balance = BalanceResult::unsupported();
                }

                return [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'driver' => $supplier->driver,
                    'balance' => $balance,
                ];
            })->all();
        });
    }

    /**
     * Process received codes: encrypt into pool via DigitalProductCodeService, update supplier order.
     */
    private function processReceivedCodes(
        SupplierOrder $supplierOrder,
        SupplierProductMapping $mapping,
        array $codes,
    ): int {
        $supplierOrder->setEncryptedCodes($codes);

        $bulkResult = $this->codeService->bulkAddToPool(
            productId: $mapping->product_id,
            records: $codes,
            source: 'supplier_api',
        );

        $supplierOrder->update([
            'status' => $bulkResult['inserted'] >= $supplierOrder->quantity ? 'fulfilled' : 'partial',
            'fulfilled_at' => $bulkResult['inserted'] > 0 ? now() : null,
            'codes_received' => $supplierOrder->codes_received,
        ]);

        $mapping->update(['last_synced_at' => now()]);

        // If all manual stock is depleted, switch the product price to the API-based price
        if ($bulkResult['inserted'] > 0) {
            $this->codeService->applyApiPriceIfManualDepleted($mapping->product_id);
        }

        return $bulkResult['inserted'];
    }

    /**
     * Fulfill direct top-up order details via supplier API (no code pool).
     */
    public function fulfillDirectTopUpOrder(Order $order): bool
    {
        $order->loadMissing(['orderDetails', 'customer']);
        $directTopUpService = app(DirectTopUpService::class);
        $anyCompleted = false;

        foreach ($order->orderDetails as $detail) {
            if ($detail->direct_topup_quantity === null || empty($detail->direct_topup_account_id)) {
                continue;
            }

            if (SupplierOrder::query()
                ->where('order_detail_id', $detail->id)
                ->whereIn('status', ['fulfilled', 'processing', 'pending'])
                ->exists()) {
                continue;
            }

            $product = Product::find($detail->product_id);
            if (! $product || ! $directTopUpService->isDirectTopUpProduct($product)) {
                continue;
            }

            $mappings = SupplierProductMapping::query()
                ->where('product_id', $product->id)
                ->active()
                ->with('supplierApi')
                ->orderBy('priority')
                ->get();

            if ($mappings->isEmpty()) {
                continue;
            }

            $accountId = (string) $detail->direct_topup_account_id;
            $quantity = (float) $detail->direct_topup_quantity;
            $errors = $directTopUpService->validatePurchase($product, $accountId, $quantity);

            if ($errors !== []) {
                Log::warning('SupplierManager: direct top-up validation failed', [
                    'order_detail_id' => $detail->id,
                    'errors' => $errors,
                ]);

                continue;
            }

            foreach ($mappings as $mapping) {
                $supplier = $mapping->supplierApi;

                if (! $supplier || ! $supplier->is_active || $supplier->isDown()) {
                    continue;
                }

                if (! $this->rateLimiter->attempt($supplier->id, $supplier->rate_limit_per_minute)) {
                    continue;
                }

                if ($this->placeDirectTopUpOrder($supplier, $mapping, $detail, $product, $accountId, $quantity, $order)) {
                    $anyCompleted = true;
                    break;
                }
            }
        }

        return $anyCompleted;
    }

    private function placeDirectTopUpOrder(
        SupplierApi $supplier,
        SupplierProductMapping $mapping,
        OrderDetail $detail,
        Product $product,
        string $accountId,
        float $quantity,
        Order $order,
    ): bool {
        $directTopUpService = app(DirectTopUpService::class);
        $unitPrice = $directTopUpService->getPricePerUnit($product);

        $logId = $this->logger->logRequest(
            supplierApiId: $supplier->id,
            action: 'place_topup',
            endpoint: $supplier->base_url,
            method: 'POST',
            requestPayload: [
                'supplier_product_id' => $mapping->supplier_product_id,
                'quantity' => $quantity,
                'account_id' => $directTopUpService->sanitizeAccountIdForLogging($accountId),
            ],
            orderId: $order->id,
        );

        $startTime = microtime(true);

        try {
            $driver = $this->driver($supplier);
            $result = $driver->placeTopUpOrder(
                supplierProductId: $mapping->supplier_product_id,
                quantity: $quantity,
                accountId: $accountId,
                unitPrice: $unitPrice,
            );

            $this->logger->logResponse(
                logId: $logId,
                httpStatusCode: 200,
                responsePayload: [
                    'supplier_order_id' => $result->supplierOrderId,
                    'status' => $result->status,
                ],
                responseTimeMs: (int) ((microtime(true) - $startTime) * 1000),
            );

            $supplierOrder = SupplierOrder::create([
                'supplier_api_id' => $supplier->id,
                'supplier_product_mapping_id' => $mapping->id,
                'supplier_order_id' => $result->supplierOrderId,
                'order_id' => $order->id,
                'order_detail_id' => $detail->id,
                'quantity' => (int) ceil($quantity),
                'cost_per_unit' => (float) $mapping->cost_price,
                'total_cost' => (float) $mapping->cost_price * $quantity,
                'cost_currency' => $mapping->cost_currency,
                'status' => $result->status,
                'fulfilled_at' => $result->isFulfilled() ? now() : null,
            ]);

            if (! $result->isFulfilled()) {
                // For async suppliers (e.g., Bamboo V1), dispatch a poll job to fetch codes
                if ($result->supplierOrderId) {
                    SupplierOrderPollJob::dispatch($supplierOrder->id)
                        ->delay(now()->addSeconds(30));

                    Log::info('SupplierManager: dispatched poll job for async direct top-up', [
                        'supplier_order_id' => $supplierOrder->id,
                        'order_id' => $order->id,
                    ]);
                }

                return false;
            }

            $detail->update([
                'delivery_status' => 'delivered',
                'payment_status' => 'paid',
            ]);

            Order::where('id', $order->id)->update([
                'order_status' => 'delivered',
            ]);

            $this->sendDirectTopUpConfirmation($order, $detail, $product, $quantity);

            Log::info('SupplierManager: direct top-up fulfilled', [
                'order_id' => $order->id,
                'order_detail_id' => $detail->id,
                'supplier_order_id' => $supplierOrder->id,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->logError(
                logId: $logId,
                errorMessage: $e->getMessage(),
                responseTimeMs: (int) ((microtime(true) - $startTime) * 1000),
            );

            return false;
        }
    }

    /**
     * Replace all codes assigned to a platform order with the real historical
     * codes delivered via supplier webhook.
     *
     * Bamboo's GET /orders/{id} endpoint returns a FRESH pool of codes each
     * time (non-deterministic), so codes saved by SupplierOrderPollJob may be
     * incorrect. The webhook delivers the ACTUAL historical codes, so we
     * delete the poll-saved codes and insert the webhook codes instead.
     *
     * @param  array<int, string|array{code: string, pin?: string|null, serial_number?: string|null, expiry_date?: string|null}>  $realCodes
     */
    private function replaceOrderCodes(
        int $orderId,
        SupplierOrder $supplierOrder,
        SupplierProductMapping $mapping,
        array $realCodes,
    ): void {
        \Illuminate\Support\Facades\DB::transaction(function () use ($orderId, $supplierOrder, $mapping, $realCodes): void {
            // Delete any existing codes that were previously assigned to this order
            // (saved by SupplierOrderPollJob from the non-deterministic GET endpoint)
            $deleted = DigitalProductCode::where('order_id', $orderId)->delete();

            Log::info('SupplierManager: replaced poll-saved codes with webhook codes', [
                'order_id' => $orderId,
                'supplier_order_id' => $supplierOrder->id,
                'deleted_count' => $deleted,
                'incoming_count' => count($realCodes),
            ]);

            // Insert the real historical codes from the webhook
            $this->processReceivedCodes(
                supplierOrder: $supplierOrder,
                mapping: $mapping,
                codes: $realCodes,
            );
        });
    }

    private function sendDirectTopUpConfirmation(Order $order, OrderDetail $detail, Product $product, float $quantity): void
    {
        $customer = $order->customer;
        $email = $customer?->email ?? null;

        if (! $email) {
            return;
        }

        try {
            Mail::to($email)->send(new \App\Mail\DirectTopUpConfirmationMail([
                'subject' => translate('direct_topup_order_completed'),
                'customerName' => trim(($customer->f_name ?? '').' '.($customer->l_name ?? '')),
                'orderId' => $order->id,
                'productName' => $product->name,
                'quantity' => $quantity,
            ]));
        } catch (\Throwable $e) {
            Log::error('SupplierManager: direct top-up confirmation email failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
