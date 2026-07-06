<?php

namespace App\Services\Supplier;

use App\Contracts\SupplierDriverInterface;
use App\DTOs\Supplier\BalanceResult;
use App\Jobs\SupplierOrderPollJob;
use App\Models\DigitalProductCode;
use App\Models\Order;
use App\Models\Product;
use App\Models\SupplierApi;
use App\Models\SupplierOrder;
use App\Models\SupplierProductDenomination;
use App\Models\SupplierProductMapping;
use App\Services\DigitalProductCodeService;
use App\Services\Supplier\Drivers\BambooDriver;
use App\Services\Supplier\Drivers\GenericRestDriver;
use App\Services\Supplier\Drivers\GolfApiDriver;
use App\Services\Supplier\Drivers\KinguinDriver;
use App\Services\Supplier\Drivers\ReloadlyDriver;
use App\Utils\OrderManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
        'golf_api' => GolfApiDriver::class,
    ];

    public function __construct(
        private readonly DigitalProductCodeService $codeService,
        private readonly SupplierApiLogger $logger,
        private readonly SupplierRateLimiter $rateLimiter,
        private readonly SupplierOrderEligibilityService $orderEligibilityService,
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
     * Get all registered drivers with their credential field and config schemas.
     * Used by the admin add-supplier form to dynamically render credential/settings fields.
     *
     * @return array<string, array{credentials: array, settings: array}>
     */
    public function getAvailableDriversWithSchemas(): array
    {
        $result = [];

        foreach ($this->drivers as $key => $class) {
            /** @var SupplierDriverInterface $instance */
            $instance = new $class;

            $result[$key] = [
                'credentials' => $instance->getRequiredCredentialFields(),
                'settings' => $instance->getConfigSchema(),
            ];
        }

        return $result;
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

        $errors = [];

        foreach ($mappings as $mapping) {
            $supplier = $mapping->supplierApi;

            if (! $supplier->is_active || $supplier->isDown()) {
                $errors[] = "Supplier '{$supplier->name}' is inactive or down.";

                continue;
            }

            if (! $this->rateLimiter->attempt($supplier->id, $supplier->rate_limit_per_minute)) {
                Log::warning('SupplierManager: rate limited', [
                    'supplier_id' => $supplier->id,
                    'product_id' => $product->id,
                ]);

                $errors[] = "Supplier '{$supplier->name}' rate limited.";

                continue;
            }

            try {
                $result = $this->placeSupplierOrder($supplier, $mapping, $quantity, $customAmount, $denomination);

                if ($result['inserted'] > 0 || $result['supplier_order_id']) {
                    return $result;
                }

                $errors[] = "Supplier '{$supplier->name}' returned no codes.";
            } catch (\Throwable $e) {
                Log::error('SupplierManager: fetchAndStockCodes failed for supplier', [
                    'supplier_id' => $supplier->id,
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ]);

                $errors[] = "Supplier '{$supplier->name}' error: ".$e->getMessage();
            }
        }

        return [
            'inserted' => 0,
            'supplier_id' => null,
            'supplier_order_id' => null,
            'error' => $errors ? implode(' ', $errors) : 'No available suppliers for this product.',
        ];
    }

    /**
     * Fulfill an order by fetching codes from suppliers on-demand.
     * Called when local pool is exhausted and product has supplier mappings.
     *
     * @return bool True if codes were obtained and assigned
     */
    public function fulfillOrder(Order $order): array
    {
        $order->loadMissing('orderDetails');

        $anyFulfilled = false;
        $errors = [];

        foreach ($order->orderDetails as $detail) {
            if (! $this->orderEligibilityService->orderDetailNeedsSupplierCodeFetch($detail)) {
                continue;
            }

            $productDetails = json_decode($detail->product_details ?? '{}');
            $productId = (int) ($detail->product_id ?? ($productDetails->id ?? 0));

            $alreadyAssigned = DigitalProductCode::where('order_detail_id', $detail->id)
                ->where('status', 'sold')
                ->count();

            $needed = max(0, (int) $detail->qty - $alreadyAssigned);

            $product = Product::find($productId);
            if (! $product) {
                continue;
            }

            $result = $this->fetchAndStockCodes($product, $needed, $detail->custom_amount, $detail->supplier_denomination_id);

            if ($result['supplier_order_id']) {
                SupplierOrder::where('id', $result['supplier_order_id'])->update([
                    'order_id' => $order->id,
                    'order_detail_id' => $detail->id,
                ]);
            }

            if ($result['inserted'] > 0) {
                $anyFulfilled = true;
            } elseif ($result['supplier_order_id']) {
                $anyFulfilled = true;

                SupplierOrderPollJob::dispatch($result['supplier_order_id'])
                    ->delay(now()->addSeconds(30));

                Log::info('SupplierManager: dispatched poll job for async supplier order', [
                    'supplier_order_id' => $result['supplier_order_id'],
                    'order_id' => $order->id,
                    'product_id' => $productId,
                ]);
            } elseif (isset($result['error'])) {
                $errors[] = "Product '{$product->name}': ".$result['error'];
            }
        }

        if ($anyFulfilled) {
            $this->codeService->assignAndNotify($order);
        }

        return [
            'fulfilled' => $anyFulfilled,
            'error' => $errors ? implode(' ', $errors) : null,
        ];
    }

    /**
     * Fulfill a direct top-up order by calling the supplier's placeTopUpOrder.
     *
     * @return bool True if the top-up was successfully placed
     */
    public function fulfillDirectTopUpOrder(Order $order): array
    {
        $order->loadMissing('orderDetails');

        $anyFulfilled = false;
        $errors = [];

        foreach ($order->orderDetails as $detail) {
            if ($detail->direct_topup_quantity === null || empty($detail->direct_topup_account_id)) {
                continue;
            }

            if (SupplierOrder::query()
                ->where('order_detail_id', $detail->id)
                ->where('status', 'fulfilled')
                ->exists()) {
                $anyFulfilled = true;

                continue;
            }

            $productId = $detail->product_id;
            if (! $productId) {
                continue;
            }

            $product = Product::find($productId);
            if (! $product) {
                continue;
            }

            $mapping = SupplierProductMapping::where('product_id', $productId)
                ->active()
                ->byPriority()
                ->with('supplierApi')
                ->first();

            if (! $mapping || ! $mapping->supplierApi) {
                $errors[] = "Product '{$product->name}': No active supplier mapping.";

                continue;
            }

            $supplier = $mapping->supplierApi;

            if (! $supplier->is_active || $supplier->isDown()) {
                $errors[] = "Product '{$product->name}': Supplier '{$supplier->name}' is inactive or down.";

                continue;
            }

            if (! $supplier->supports_direct_top_up) {
                Log::warning('SupplierManager: supplier does not support direct top-up', [
                    'supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->name,
                    'order_id' => $order->id,
                ]);

                $errors[] = "Product '{$product->name}': Supplier '{$supplier->name}' does not support direct top-up.";

                continue;
            }

            if (! $this->rateLimiter->attempt($supplier->id, $supplier->rate_limit_per_minute)) {
                $errors[] = "Product '{$product->name}': Supplier '{$supplier->name}' rate limited.";

                continue;
            }

            $logId = $this->logger->logRequest(
                supplierApiId: $supplier->id,
                action: 'place_topup_order',
                endpoint: $supplier->base_url,
                method: 'POST',
                requestPayload: [
                    'supplier_product_id' => $mapping->supplier_product_id,
                    'quantity' => (float) $detail->direct_topup_quantity,
                    'account_id' => $detail->direct_topup_account_id,
                ],
            );

            $startTime = microtime(true);

            try {
                $driver = $this->driver($supplier);
                $unitPrice = (float) $detail->custom_amount;

                $accountId = OrderManager::resolveDirectTopUpAccountId($detail) ?? '';

                $result = $driver->placeTopUpOrder(
                    supplierProductId: $mapping->supplier_product_id,
                    quantity: (float) $detail->direct_topup_quantity,
                    accountId: $accountId,
                    unitPrice: $unitPrice > 0 ? $unitPrice : null,
                );

                $httpStatus = 200;
                $envelopeStatus = strtolower((string) data_get($result->rawResponse, 'status', ''));
                if ($result->status !== 'fulfilled' || $envelopeStatus === 'error' || $result->supplierOrderId === '') {
                    $httpStatus = 422;
                    $this->logger->logResponse(
                        logId: $logId,
                        httpStatusCode: $httpStatus,
                        responsePayload: [
                            'supplier_order_id' => $result->supplierOrderId,
                            'status' => $result->status,
                            'raw_response' => $result->rawResponse,
                        ],
                        responseTimeMs: (int) ((microtime(true) - $startTime) * 1000),
                    );

                    $apiMessage = (string) data_get($result->rawResponse, 'message', 'Supplier top-up failed.');
                    $errors[] = "Product '{$product->name}': {$apiMessage}";

                    continue;
                }

                $this->logger->logResponse(
                    logId: $logId,
                    httpStatusCode: 200,
                    responsePayload: [
                        'supplier_order_id' => $result->supplierOrderId,
                        'status' => $result->status,
                        'raw_response' => $result->rawResponse,
                    ],
                    responseTimeMs: (int) ((microtime(true) - $startTime) * 1000),
                );

                $costPerUnit = $unitPrice > 0 ? $unitPrice : (float) $mapping->cost_price;

                $supplierOrder = SupplierOrder::create([
                    'supplier_api_id' => $supplier->id,
                    'supplier_product_mapping_id' => $mapping->id,
                    'supplier_order_id' => $result->supplierOrderId,
                    'order_id' => $order->id,
                    'order_detail_id' => $detail->id,
                    'quantity' => (int) $detail->direct_topup_quantity,
                    'cost_per_unit' => $costPerUnit,
                    'total_cost' => $costPerUnit * (float) $detail->direct_topup_quantity,
                    'cost_currency' => $mapping->cost_currency,
                    'status' => $result->status,
                ]);

                Log::info('SupplierManager: direct top-up order placed', [
                    'supplier_order_id' => $supplierOrder->id,
                    'order_id' => $order->id,
                    'supplier_id' => $supplier->id,
                    'status' => $result->status,
                ]);

                $anyFulfilled = true;

            } catch (\Throwable $e) {
                $this->logger->logError(
                    logId: $logId,
                    errorMessage: $e->getMessage(),
                    responseTimeMs: (int) ((microtime(true) - $startTime) * 1000),
                );

                Log::error('SupplierManager: direct top-up fulfillment failed', [
                    'order_id' => $order->id,
                    'supplier_id' => $supplier->id,
                    'error' => $e->getMessage(),
                ]);

                $errors[] = "Product '{$product->name}': Supplier error: ".$e->getMessage();
            }
        }

        return [
            'fulfilled' => $anyFulfilled,
            'error' => $errors ? implode(' ', $errors) : null,
        ];
    }

    /**
     * Fetch available stock for a product-supplier mapping via the configured driver.
     * Results are cached briefly to avoid hammering supplier APIs on cart renders.
     */
    public function getAvailableStockForMapping(SupplierProductMapping $mapping): int
    {
        $supplier = $mapping->supplierApi;

        if (! $supplier || ! $supplier->is_active || $supplier->isDown()) {
            return 0;
        }

        $cacheKey = 'supplier_stock:'.$mapping->id;
        $ttl = (int) config('supplier.stock_cache_ttl', 60);

        return (int) Cache::remember($cacheKey, $ttl, function () use ($mapping, $supplier): int {
            try {
                if (! $this->rateLimiter->attempt($supplier->id, $supplier->rate_limit_per_minute)) {
                    return 0;
                }

                $driver = $this->driver($supplier);
                $stockResult = $driver->fetchStock($mapping->supplier_product_id);

                return max(0, $stockResult->available);
            } catch (\Throwable $e) {
                Log::warning('Supplier stock fetch failed for mapping '.$mapping->id.': '.$e->getMessage());

                return 0;
            }
        });
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
                        $this->processReceivedCodes(
                            supplierOrder: $supplierOrder,
                            mapping: $supplierOrder->productMapping,
                            codes: $result->codes,
                        );

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
}
