<?php

namespace App\Services\Supplier;

use App\DTOs\Supplier\AvailabilityResult;
use App\DTOs\Supplier\BalanceResult;
use App\DTOs\Supplier\StockResult;
use App\Models\Cart;
use App\Models\DigitalProductCode;
use App\Models\SupplierProductMapping;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SupplierAvailabilityService
{
    public const CACHE_SECONDS = 45;

    public function __construct(
        private readonly SupplierManager $supplierManager,
    ) {}

    /**
     * @param  iterable<int, object>  $carts
     */
    public function checkCarts(iterable $carts): AvailabilityResult
    {
        $errors = [];
        $failedProductIds = [];

        foreach ($carts as $cart) {
            $itemResult = $this->checkCartItem($cart);
            if ($itemResult['ok']) {
                continue;
            }

            $errors[] = $itemResult['error'];
            $failedProductIds[] = (int) $this->cartProductId($cart);
        }

        if ($errors === []) {
            return AvailabilityResult::available();
        }

        return AvailabilityResult::unavailable(
            errors: $errors,
            failedProductIds: array_values(array_unique($failedProductIds)),
        );
    }

    public function checkPaymentRequest(object $payment): AvailabilityResult
    {
        if (! $this->isOrderPayment($payment)) {
            return AvailabilityResult::available();
        }

        $carts = $this->cartsForPayment($payment);
        if ($carts->isEmpty()) {
            return AvailabilityResult::available();
        }

        return $this->checkCarts($carts);
    }

    public function shouldCreateStripeSession(object $payment): bool
    {
        return $this->checkPaymentRequest($payment)->ok;
    }

    public function availableUnitsForProduct(int $productId, ?object $mapping = null): int
    {
        $localCount = $this->localCodeCount($productId);
        $mapping ??= $this->activeMapping($productId);

        if ($mapping === null || ! $this->mappingSupplierIsActive($mapping)) {
            return $localCount;
        }

        return $localCount + max(0, $this->cachedSupplierStock($mapping));
    }

    /**
     * @return array{ok: bool, error: string}
     */
    public function checkCartItem(object $cart): array
    {
        $productId = $this->cartProductId($cart);
        $quantity = $this->cartQuantity($cart);
        $productName = $this->cartProductName($cart);
        $isDirectTopUp = $this->isDirectTopUpCart($cart);

        if ($productId <= 0 || $quantity <= 0) {
            return ['ok' => true, 'error' => ''];
        }

        $localCount = $this->localCodeCount($productId);
        $mapping = $this->activeMapping($productId);

        if ($mapping === null) {
            if ($isDirectTopUp) {
                return [
                    'ok' => false,
                    'error' => $this->message('supplier_unavailable_for_item', $productName),
                ];
            }

            return ['ok' => true, 'error' => ''];
        }

        if (! $this->mappingSupplierIsActive($mapping)) {
            if (! $isDirectTopUp && $localCount >= $quantity) {
                return ['ok' => true, 'error' => ''];
            }

            return [
                'ok' => false,
                'error' => $this->message('supplier_unavailable_for_item', $productName),
            ];
        }

        $usesLocalFirst = $this->usesLocalFirst($mapping);
        if (! $isDirectTopUp && $usesLocalFirst && $localCount >= $quantity) {
            return ['ok' => true, 'error' => ''];
        }

        $neededFromSupplier = $isDirectTopUp
            ? $quantity
            : ($usesLocalFirst ? max(0, $quantity - $localCount) : $quantity);

        if ($neededFromSupplier <= 0) {
            return ['ok' => true, 'error' => ''];
        }

        if (! $isDirectTopUp) {
            try {
                $stock = $this->cachedStockResult($mapping);
            } catch (Throwable $exception) {
                Log::warning('SupplierAvailabilityService: fetchStock failed', [
                    'product_id' => $productId,
                    'mapping_id' => $mapping->id ?? null,
                    'error' => $exception->getMessage(),
                ]);

                return [
                    'ok' => false,
                    'error' => $this->message('supplier_stock_check_failed', $productName),
                ];
            }

            if ($stock->available < $neededFromSupplier) {
                return [
                    'ok' => false,
                    'error' => $this->message('supplier_stock_unavailable', $productName),
                ];
            }
        }

        try {
            $balance = $this->cachedBalanceResult($mapping);
        } catch (Throwable $exception) {
            Log::warning('SupplierAvailabilityService: getBalance failed', [
                'product_id' => $productId,
                'mapping_id' => $mapping->id ?? null,
                'error' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'error' => $this->message('supplier_balance_check_failed', $productName),
            ];
        }

        if ($balance->supported) {
            $requiredCredit = $this->requiredSupplierCredit($mapping, $neededFromSupplier);
            if ($balance->balance < $requiredCredit) {
                return [
                    'ok' => false,
                    'error' => $this->message('supplier_balance_unavailable', $productName),
                ];
            }
        }

        return ['ok' => true, 'error' => ''];
    }

    protected function isOrderPayment(object $payment): bool
    {
        $attribute = $payment->attribute ?? null;

        return $attribute === null || $attribute === 'order';
    }

    /**
     * @return Collection<int, object>
     */
    protected function cartsForPayment(object $payment): Collection
    {
        $additional = $this->decodeAdditionalData($payment);
        $customerId = $additional['customer_id'] ?? $payment->payer_id ?? null;

        if ($customerId === null || $customerId === '' || $customerId === 0) {
            return collect();
        }

        return Cart::query()
            ->whereHas('product', function ($query) {
                $query->active();
            })
            ->where('customer_id', $customerId)
            ->where('is_checked', 1)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeAdditionalData(object $payment): array
    {
        $raw = $payment->additional_data ?? null;
        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function localCodeCount(int $productId): int
    {
        return (int) DigitalProductCode::query()
            ->where('product_id', $productId)
            ->available()
            ->count();
    }

    protected function activeMapping(int $productId): ?object
    {
        $query = SupplierProductMapping::query()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->with('supplierApi');

        if (method_exists(SupplierProductMapping::class, 'scopeByPriority')) {
            $query->byPriority();
        } else {
            $query->orderBy('priority');
        }

        return $query->first();
    }

    protected function cachedSupplierStock(object $mapping): int
    {
        try {
            return $this->cachedStockResult($mapping)->available;
        } catch (Throwable $exception) {
            Log::warning('SupplierAvailabilityService: cached supplier stock failed', [
                'mapping_id' => $mapping->id ?? null,
                'error' => $exception->getMessage(),
            ]);

            return 0;
        }
    }

    protected function cachedStockResult(object $mapping): StockResult
    {
        $cacheKey = $this->stockCacheKey($mapping);

        return Cache::remember($cacheKey, self::CACHE_SECONDS, function () use ($mapping): StockResult {
            return $this->fetchLiveStock($mapping);
        });
    }

    protected function cachedBalanceResult(object $mapping): BalanceResult
    {
        $supplierId = (int) ($mapping->supplier_api_id ?? $mapping->supplierApi?->id ?? 0);
        $cacheKey = 'supplier:availability:balance:'.$supplierId;

        return Cache::remember($cacheKey, self::CACHE_SECONDS, function () use ($mapping): BalanceResult {
            return $this->fetchLiveBalance($mapping);
        });
    }

    protected function fetchLiveStock(object $mapping): StockResult
    {
        $supplier = $mapping->supplierApi;
        $supplierProductId = (string) $mapping->supplier_product_id;

        return $this->supplierManager->driver($supplier)->fetchStock($supplierProductId);
    }

    protected function fetchLiveBalance(object $mapping): BalanceResult
    {
        $supplier = $mapping->supplierApi;

        return $this->supplierManager->driver($supplier)->getBalance();
    }

    protected function mappingSupplierIsActive(object $mapping): bool
    {
        return (bool) ($mapping->supplierApi?->is_active ?? false);
    }

    protected function usesLocalFirst(object $mapping): bool
    {
        $priority = (string) ($mapping->code_source_priority ?? 'local_first');

        return $priority === 'local_first'
            || (defined(SupplierProductMapping::class.'::CODE_SOURCE_LOCAL_FIRST')
                && $priority === SupplierProductMapping::CODE_SOURCE_LOCAL_FIRST);
    }

    protected function isDirectTopUpCart(object $cart): bool
    {
        if (method_exists($cart, 'isDirectTopUp')) {
            return (bool) $cart->isDirectTopUp();
        }

        return ($cart->direct_topup_quantity ?? null) !== null
            || (bool) ($cart->is_direct_topup ?? false);
    }

    protected function cartProductId(object $cart): int
    {
        return (int) ($cart->product_id ?? 0);
    }

    protected function cartQuantity(object $cart): float
    {
        if ($this->isDirectTopUpCart($cart) && isset($cart->direct_topup_quantity)) {
            return (float) $cart->direct_topup_quantity;
        }

        return (float) ($cart->qty ?? $cart->quantity ?? 1);
    }

    protected function cartProductName(object $cart): string
    {
        $name = $cart->product->name ?? $cart->name ?? null;

        return $name ? (string) $name : 'product #'.$this->cartProductId($cart);
    }

    protected function requiredSupplierCredit(object $mapping, float $quantity): float
    {
        return (float) ($mapping->cost_price ?? 0) * $quantity;
    }

    protected function stockCacheKey(object $mapping): string
    {
        $supplierId = (int) ($mapping->supplier_api_id ?? $mapping->supplierApi?->id ?? 0);
        $supplierProductId = (string) ($mapping->supplier_product_id ?? '');

        return 'supplier:availability:stock:'.$supplierId.':'.$supplierProductId;
    }

    protected function message(string $key, string $productName): string
    {
        $fallback = match ($key) {
            'supplier_unavailable_for_item' => $productName.' is currently unavailable from the supplier.',
            'supplier_stock_unavailable' => $productName.' is out of stock at the supplier.',
            'supplier_stock_check_failed' => 'Unable to verify supplier stock for '.$productName.'.',
            'supplier_balance_unavailable' => 'Supplier balance is insufficient for '.$productName.'.',
            'supplier_balance_check_failed' => 'Unable to verify supplier balance for '.$productName.'.',
            default => $productName.' is currently unavailable.',
        };

        if (! function_exists('translate')) {
            return $fallback;
        }

        $translated = translate($key);

        return $translated === $key ? $fallback : $translated.' ('.$productName.')';
    }
}
