<?php

namespace App\Services;

use App\Mail\DigitalCodeDeliveryMail;
use App\Models\DigitalProductCode;
use App\Models\Order;
use App\Models\Product;
use App\Models\SupplierProductMapping;
use App\Services\Order\OrderFulfillmentStatusService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DigitalProductCodeService
{
    public function __construct(
        private readonly OrderFulfillmentStatusService $fulfillmentStatusService,
    ) {}

    /**
     * Add a single plain-text code to the pool for a product.
     * The code is AES-256-CBC encrypted before storage.
     * A SHA-256 hash of the normalised plain code is stored for duplicate detection.
     *
     * Duplicate detection is **vendor-specific**: the same code may exist across
     * different vendors, but a single vendor cannot upload the same code twice.
     * Admin-uploaded codes (seller_id = null) are checked globally.
     *
     * Returns null (without throwing) when a duplicate is detected so callers can
     * gracefully count skipped rows.
     */
    public function addToPool(
        int $productId,
        string $plainCode,
        ?string $serialNumber = null,
        ?string $expiryDate = null,
        string $source = 'manual',
        ?string $pin = null,
    ): ?DigitalProductCode {
        $normalised = strtolower(trim($plainCode));
        $hash = hash('sha256', $normalised);

        // Resolve seller_id from the product
        $product = Product::select('id', 'added_by', 'user_id')->find($productId);
        $sellerId = ($product && $product->added_by === 'seller') ? (int) $product->user_id : null;

        // ── Duplicate detection (vendor-specific) ────────────────────────────
        // Same vendor (or same admin scope) must NOT have duplicate codes.
        // Different vendors ARE allowed to have the same code.
        $duplicateQuery = DigitalProductCode::where('code_hash', $hash);
        if ($sellerId !== null) {
            $duplicateQuery->where('seller_id', $sellerId);
        } else {
            $duplicateQuery->whereNull('seller_id');
        }
        if ($duplicateQuery->exists()) {
            return null;
        }

        // 2. Serial number duplicate within the same product
        if ($serialNumber !== null && $serialNumber !== '') {
            $cleanSerial = trim($serialNumber);
            if (DigitalProductCode::where('product_id', $productId)
                ->where('serial_number', $cleanSerial)
                ->exists()
            ) {
                return null;
            }
        }
        // ────────────────────────────────────────────────────────────────────

        try {
            $record = DigitalProductCode::create([
                'product_id' => $productId,
                'seller_id' => $sellerId,
                'code' => Crypt::encryptString(trim($plainCode)),
                'pin' => $pin ? Crypt::encryptString(trim($pin)) : null,
                'code_hash' => $hash,
                'serial_number' => $serialNumber ? trim($serialNumber) : null,
                'expiry_date' => $expiryDate ?: null,
                'status' => 'available',
                'source' => $source,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Catch race-condition duplicate (concurrent insert between the EXISTS check and CREATE)
            if ($e->errorInfo[1] === 1062) {
                return null;
            }
            throw $e;
        }

        $this->syncStock($productId);

        return $record;
    }

    /**
     * Add many codes for a product in one shot.
     * Each item in $records should be:
     *   ['code' => string, 'serial_number' => ?string, 'expiry_date' => ?string]
     * Passing a plain string is also accepted for backwards compatibility.
     *
     * @param  array<int, string|array{code: string, pin?: string|null, serial_number?: string|null, expiry_date?: string|null}>  $records
     * @return array{inserted: int, skipped: int}
     */
    public function bulkAddToPool(int $productId, array $records, string $source = 'manual'): array
    {
        $inserted = 0;
        $skipped = 0;

        foreach ($records as $record) {
            // Support both plain strings (legacy) and structured arrays
            if (is_string($record)) {
                $plainCode = trim($record);
                $serialNumber = null;
                $expiryDate = null;
                $pin = null;
            } else {
                $plainCode = trim((string) ($record['code'] ?? ''));
                $serialNumber = isset($record['serial_number']) ? trim((string) $record['serial_number']) : null;
                $expiryDate = $record['expiry_date'] ?? null;
                $pin = isset($record['pin']) ? trim((string) $record['pin']) : null;
            }

            if ($plainCode === '') {
                $skipped++;

                continue;
            }

            $result = $this->addToPool($productId, $plainCode, $serialNumber, $expiryDate, $source, $pin);
            if ($result === null) {
                $skipped++; // duplicate
            } else {
                $inserted++;
            }
        }

        $this->syncStock($productId);

        return ['inserted' => $inserted, 'skipped' => $skipped];
    }

    /**
     * Pick one available code for a product and mark it as reserved.
     * Uses a DB-level lock (SELECT … FOR UPDATE) to prevent race conditions.
     * Codes with a past expiry date are skipped automatically.
     *
     * Returns null if no available code exists.
     */
    public function reserve(int $productId, int $orderId): ?DigitalProductCode
    {
        return DB::transaction(function () use ($productId, $orderId): ?DigitalProductCode {
            /** @var DigitalProductCode|null $record */
            $record = DigitalProductCode::query()
                ->where('product_id', $productId)
                ->where('status', 'available')
                ->where('is_active', true)
                ->where(function ($q): void {
                    $q->whereNull('expiry_date')
                        ->orWhereDate('expiry_date', '>=', now()->toDateString());
                })
                ->lockForUpdate()
                ->first();

            if (! $record) {
                return null;
            }

            $record->update([
                'status' => 'reserved',
                'order_id' => $orderId,
            ]);

            $this->syncStock($productId);

            return $record;
        });
    }

    /**
     * Confirm delivery of a reserved code (payment confirmed).
     * Marks the code as sold and records delivery timestamp.
     */
    public function markSold(int $codeId, int $orderDetailId): void
    {
        DigitalProductCode::query()
            ->where('id', $codeId)
            ->update([
                'status' => 'sold',
                'order_detail_id' => $orderDetailId,
                'assigned_at' => now(),
            ]);
    }

    /**
     * Release a reserved code back to the pool (e.g. payment failed / order cancelled).
     */
    public function release(int $productId, int $orderId): void
    {
        DigitalProductCode::query()
            ->where('product_id', $productId)
            ->where('order_id', $orderId)
            ->whereIn('status', ['reserved'])
            ->update([
                'status' => 'available',
                'order_id' => null,
                'order_detail_id' => null,
                'assigned_at' => null,
            ]);

        $this->syncStock($productId);
    }

    /**
     * Assign codes to all digital order details for a given Order when payment is confirmed.
     * Assigns exactly $detail->qty codes per detail so multi-quantity orders are fulfilled correctly.
     * Idempotent — already-fully-assigned details are skipped.
     */
    public function assignCodesForOrder(Order $order, ?array $defaultSourcePriority = null): void
    {
        if (! $order->orderDetails) {
            return;
        }

        foreach ($order->orderDetails as $detail) {
            $productDetails = json_decode($detail->product_details ?? '{}');
            $productType = $productDetails->product_type ?? null;
            $digitalType = $productDetails->digital_product_type ?? null;

            if ($productType !== 'digital') {
                continue;
            }

            if (! empty($detail->direct_topup_quantity)) {
                continue;
            }

            $productId = $detail->product_id ?? ($productDetails->id ?? null);
            if (! $productId) {
                continue;
            }

            $mapping = SupplierProductMapping::query()
                ->where('product_id', $productId)
                ->where('is_active', true)
                ->where('is_direct_topup', false)
                ->whereHas('supplierApi', fn ($query) => $query->where('is_active', true))
                ->orderBy('priority')
                ->first();

            $sourcePriority = $defaultSourcePriority
                ?? $mapping?->codeSourcePriorityOrder()
                ?? ['manual', 'supplier_api'];

            // How many codes are already assigned for this order detail?
            $alreadyAssigned = DigitalProductCode::query()
                ->where('order_detail_id', $detail->id)
                ->where('status', 'sold')
                ->count();

            $needed = max(0, (int) $detail->qty - $alreadyAssigned);

            if ($needed <= 0) {
                continue; // Already fully assigned — idempotent
            }

            for ($i = 0; $i < $needed; $i++) {
                try {
                    DB::transaction(function () use ($productId, $detail, $order, $sourcePriority): void {
                        $record = null;

                        foreach ($sourcePriority as $source) {
                            /** @var DigitalProductCode|null $candidate */
                            $candidate = DigitalProductCode::query()
                                ->where('product_id', $productId)
                                ->where('source', $source)
                                ->where('status', 'available')
                                ->where('is_active', true)
                                ->where(function ($q): void {
                                    $q->whereNull('expiry_date')
                                        ->orWhereDate('expiry_date', '>=', now()->toDateString());
                                })
                                ->lockForUpdate()
                                ->first();

                            if ($candidate !== null) {
                                $record = $candidate;
                                break;
                            }
                        }

                        if ($record === null) {
                            $record = DigitalProductCode::query()
                                ->where('product_id', $productId)
                                ->where('status', 'available')
                                ->where('is_active', true)
                                ->where(function ($q): void {
                                    $q->whereNull('expiry_date')
                                        ->orWhereDate('expiry_date', '>=', now()->toDateString());
                                })
                                ->lockForUpdate()
                                ->first();
                        }

                        if (! $record) {
                            // No code available — leave remaining units for manual fulfilment / re-stock
                            Log::warning('DigitalProductCodeService: No available code in pool', [
                                'product_id' => $productId,
                                'order_id' => $order->id,
                                'order_detail_id' => $detail->id,
                            ]);

                            return;
                        }

                        $record->update([
                            'status' => 'sold',
                            'order_id' => $order->id,
                            'order_detail_id' => $detail->id,
                            'assigned_at' => now(),
                        ]);

                        $this->syncStock($productId);
                    });
                } catch (\Throwable $e) {
                    Log::error('DigitalProductCodeService: assignCodesForOrder failed', [
                        'order_id' => $order->id,
                        'detail_id' => $detail->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Validate that every digital "ready_product" item in the given carts has enough codes
     * in the pool. Returns an array of human-readable error strings (empty = all OK).
     *
     * @param  \Illuminate\Support\Collection|\App\Models\Cart[]  $carts
     * @return string[]
     */
    public function getDigitalStockErrors($carts): array
    {
        $errors = [];

        foreach ($carts as $cart) {
            if (($cart->product_type ?? null) !== 'digital') {
                continue;
            }

            if ($cart instanceof \App\Models\Cart && $cart->isDirectTopUp()) {
                continue;
            }

            if (($cart->direct_topup_quantity ?? null) !== null) {
                continue;
            }

            $productId = $cart->product_id ?? null;
            if (! $productId) {
                continue;
            }

            if (SupplierProductMapping::hasActiveMapping((int) $productId)) {
                continue;
            }

            $available = DigitalProductCode::query()
                ->where('product_id', $productId)
                ->where('status', 'available')
                ->where('is_active', true)
                ->where(function ($q): void {
                    $q->whereNull('expiry_date')
                        ->orWhereDate('expiry_date', '>=', now()->toDateString());
                })
                ->count();

            $requested = (int) $cart->quantity;
            if ($available < $requested) {
                $productName = $cart->name ?? ($cart->product?->name ?? translate('Product'));
                $errors[] = translate('Only').' '.$available.' '.translate('code(s)_available_for').
                    ' "'.$productName.'". '.
                    translate('Please_reduce_quantity_to').' '.$available.'.';
            }
        }

        return $errors;
    }

    /**
     * Retrieve the decrypted code for a delivered order detail.
     * Returns null if no code is assigned yet.
     */
    public function getDecryptedCodeForOrderDetail(int $orderDetailId): ?string
    {
        $record = DigitalProductCode::query()
            ->where('order_detail_id', $orderDetailId)
            ->whereIn('status', ['sold', 'reserved'])
            ->first();

        if (! $record) {
            return null;
        }

        return $record->decryptCode();
    }

    /**
     * Sync the product's current_stock to match available, non-expired, active codes.
     */
    /**
     * Switch product price to the supplier API price (cost + markup) when all
     * manually uploaded stock is depleted.
     *
     * Rules:
     * - Only switches when manual available codes reach zero.
     * - Uses the highest-priority active mapping's calculateSellPrice().
     * - Logs the price change so admins can audit it.
     * - Does nothing if no active supplier mapping exists.
     */
    /**
     * Sync the product's price from the supplier mapping.
     *
     * Uses the centralized Product::getEffectiveSellPrice() to determine
     * the correct price, then writes it back to product.unit_price so it
     * is reflected everywhere (cached queries, APIs, product listings).
     */
    public function applyApiPriceIfManualDepleted(int $productId): void
    {
        $product = Product::find($productId);

        if (! $product) {
            return;
        }

        $mapping = SupplierProductMapping::query()
            ->where('product_id', $productId)
            ->active()
            ->byPriority()
            ->whereHas('supplierApi', fn ($q) => $q->where('is_active', true))
            ->first();

        if (! $mapping) {
            return;
        }

        $newCostPrice = (float) $mapping->cost_price;

        if ((float) $product->purchase_price === $newCostPrice) {
            return;
        }

        $previousCostPrice = $product->purchase_price;

        Product::where('id', $productId)->update([
            'purchase_price' => $newCostPrice,
        ]);

        Log::info('DigitalProductCodeService: product cost price synced from supplier mapping', [
            'product_id' => $productId,
            'previous_cost_price' => $previousCostPrice,
            'cost_price' => $newCostPrice,
            'mapping_id' => $mapping->id,
            'supplier_api_id' => $mapping->supplier_api_id,
        ]);
    }

    public function syncStock(int $productId): void
    {
        $available = DigitalProductCode::query()
            ->where('product_id', $productId)
            ->where('status', 'available')
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', now()->toDateString());
            })
            ->count();

        Product::query()
            ->where('id', $productId)
            ->update(['current_stock' => $available]);
    }

    /**
     * Mark all codes whose expiry_date is in the past (and status is 'available')
     * as 'expired', then sync stock for affected products.
     * Called by the daily MarkExpiredDigitalCodesCommand.
     *
     * @return int Number of codes marked expired
     */
    public function markExpiredCodes(): int
    {
        // Find all product IDs that will be affected before we update
        $affectedProductIds = DigitalProductCode::query()
            ->pastExpiry()
            ->distinct()
            ->pluck('product_id')
            ->toArray();

        $count = DigitalProductCode::query()
            ->pastExpiry()
            ->update(['status' => 'expired', 'updated_at' => now()]);

        // Sync stock for every impacted product
        foreach ($affectedProductIds as $productId) {
            $this->syncStock($productId);
        }

        return $count;
    }

    /**
     * Assign codes for a paid order and send customer email in one call.
     * Safe to call from non-Eloquent contexts (e.g. OrderManager raw insert).
     */
    public function assignAndNotify(Order $order, ?array $sourcePriority = null): void
    {
        $order->loadMissing('orderDetails');
        $this->assignCodesForOrder($order, $sourcePriority);
        $this->sendDigitalCodeEmail($order);
        $this->fulfillmentStatusService->syncOrderFulfillmentStatus($order);
    }

    /**
     * Send all assigned digital codes for this order to the customer via email.
     */
    public function sendDigitalCodeEmail(Order $order): void
    {
        try {
            $assignedCodes = DigitalProductCode::query()
                ->where('order_id', $order->id)
                ->where('status', 'sold')
                ->with('product')
                ->get();

            if ($assignedCodes->isEmpty()) {
                return;
            }

            $codes = $assignedCodes->map(function (DigitalProductCode $record): array {
                return [
                    'productName' => $record->product?->name ?? translate('Digital Product'),
                    'code' => $record->decryptCode(),
                    'pin' => $record->decryptPin(),
                    'serial' => $record->serial_number,
                    'expiry' => $record->expiry_date?->format('Y-m-d'),
                ];
            })->all();

            if ($order->is_guest) {
                $addressData = $order->billing_address_data ?? $order->shipping_address_data;
                $email = $addressData?->email ?? null;
                $name = trim(($addressData?->contact_person_name ?? translate('Customer')));
            } else {
                $order->loadMissing('customer');
                $email = $order->customer?->email ?? null;
                $name = trim($order->customer?->f_name.' '.$order->customer?->l_name);
            }

            if (! $email) {
                return;
            }

            $companyName = getWebConfig(name: 'company_name') ?? config('app.name');

            $data = [
                'subject' => translate('Your Digital Codes — Order #').$order->id,
                'customerName' => $name ?: translate('Customer'),
                'orderId' => $order->id,
                'orderDate' => $order->created_at?->format('Y-m-d'),
                'codes' => $codes,
                'companyName' => $companyName,
            ];

            Mail::to($email)->queue(new DigitalCodeDeliveryMail($data));
        } catch (\Throwable $e) {
            Log::error('DigitalProductCodeService: failed to send digital code email', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get pool statistics for a product.
     *
     * @return array{available: int, reserved: int, sold: int, total: int}
     */
    public function getPoolStats(int $productId): array
    {
        $stats = DigitalProductCode::query()
            ->where('product_id', $productId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'available' => (int) ($stats['available'] ?? 0),
            'reserved' => (int) ($stats['reserved'] ?? 0),
            'sold' => (int) ($stats['sold'] ?? 0),
            'total' => array_sum($stats),
        ];
    }

    /**
     * Toggle the is_active flag on a digital code.
     * Only codes with status 'available' or 'expired' can be toggled.
     * Reserved/sold codes cannot be deactivated.
     *
     * After toggling, stock is re-synced to exclude inactive codes.
     */
    public function toggleActive(int $codeId, ?int $sellerId = null): DigitalProductCode
    {
        $query = DigitalProductCode::where('id', $codeId);
        if ($sellerId !== null) {
            $query->where('seller_id', $sellerId);
        }

        $code = $query->firstOrFail();

        if (in_array($code->status, ['reserved', 'sold'])) {
            throw new \RuntimeException(translate('Cannot_toggle_a_code_that_is_reserved_or_sold.'));
        }

        $code->update(['is_active' => ! $code->is_active]);

        $this->syncStock($code->product_id);

        return $code->fresh();
    }

    /**
     * Delete a digital code from the pool.
     * Only codes with status 'available' or 'expired' can be deleted.
     * Reserved/sold codes cannot be deleted (they are tied to orders).
     *
     * After deletion, stock is re-synced.
     */
    public function deleteCode(int $codeId, ?int $sellerId = null): void
    {
        $query = DigitalProductCode::where('id', $codeId);
        if ($sellerId !== null) {
            $query->where('seller_id', $sellerId);
        }

        $code = $query->firstOrFail();

        if (in_array($code->status, ['reserved', 'sold'])) {
            throw new \RuntimeException(translate('Cannot_delete_a_code_that_is_reserved_or_sold.'));
        }

        $productId = $code->product_id;
        $code->delete();
        $this->syncStock($productId);
    }
}
