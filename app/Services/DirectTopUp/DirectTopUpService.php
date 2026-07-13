<?php

namespace App\Services\DirectTopUp;

use App\Models\Product;
use App\Models\SupplierApi;
use App\Models\SupplierProductMapping;
use App\Services\Supplier\Drivers\GolfApiDriver;
use App\Services\Supplier\SupplierManager;
use InvalidArgumentException;

class DirectTopUpService
{
    public function __construct(
        private readonly SupplierManager $supplierManager,
    ) {}

    public function isDirectTopUpProduct(Product $product): bool
    {
        if ($product->product_type !== 'digital') {
            return false;
        }

        $mapping = $this->getMapping($product);

        if ($mapping === null || ! (bool) $mapping->is_direct_topup) {
            return false;
        }

        if (! (bool) ($mapping->supplierApi?->is_active ?? false)) {
            return false;
        }

        return (bool) ($mapping->supplierApi?->supports_direct_top_up ?? false)
            || trim((string) ($mapping->direct_topup_account_label ?? '')) !== '';
    }

    public function hasActiveSupplierMapping(Product $product): bool
    {
        return SupplierProductMapping::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->exists();
    }

    public function canAddToCart(Product $product): bool
    {
        if (! $this->isDirectTopUpProduct($product)) {
            return false;
        }

        if (! $this->hasActiveSupplierMapping($product)) {
            return false;
        }

        try {
            $this->validateConfiguration($product);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function validateConfiguration(Product $product): void
    {
        if (! $this->isDirectTopUpProduct($product)) {
            return;
        }

        $mapping = $this->getMapping($product);

        if ($mapping === null) {
            return;
        }

        if (empty(trim((string) $mapping->direct_topup_account_label))) {
            throw new InvalidArgumentException(translate('direct_topup_account_label_is_required'));
        }
    }

    /**
     * @return array<string, string>
     */
    public function validatePurchase(Product $product, string $accountId, float $quantity): array
    {
        $errors = [];

        if (! $this->isDirectTopUpProduct($product)) {
            $errors['product'] = translate('product_is_not_direct_topup');

            return $errors;
        }

        if (! $this->hasActiveSupplierMapping($product)) {
            $errors['supplier'] = translate('direct_topup_requires_supplier_mapping');
        }

        try {
            $this->validateConfiguration($product);
        } catch (InvalidArgumentException $e) {
            $errors['configuration'] = $e->getMessage();
        }

        $accountId = trim($accountId);

        if ($accountId === '') {
            $errors['direct_topup_account_id'] = translate('direct_topup_account_id_is_required');
        } elseif (strlen($accountId) > 255) {
            $errors['direct_topup_account_id'] = translate('direct_topup_account_id_too_long');
        } elseif (! preg_match('/^[a-zA-Z0-9_\-\.@]+$/u', $accountId)) {
            $errors['direct_topup_account_id'] = translate('direct_topup_account_id_invalid_format');
        }

        if ($quantity <= 0) {
            $errors['direct_topup_quantity'] = translate('direct_topup_quantity_must_be_positive');
        }

        if ($errors === [] && $this->requiresAccountVerification($product)) {
            $supplierValidation = $this->validateAccountWithSupplier($product, $accountId);

            if ($supplierValidation['supported'] && ! $supplierValidation['valid']) {
                $errors['direct_topup_account_id'] = (string) ($supplierValidation['message']
                    ?? translate('direct_topup_account_invalid'));
            }
        }

        return $errors;
    }

    public function requiresAccountVerification(Product $product): bool
    {
        if (! $this->isDirectTopUpProduct($product)) {
            return false;
        }

        $mapping = $this->getMapping($product);

        return $this->supplierSupportsPlayerIdValidation($mapping?->supplierApi);
    }

    /**
     * @return array{supported: bool, valid: bool, player_id: string|null, username: string|null, message: string|null}
     */
    public function validateAccountWithSupplier(Product $product, string $accountId): array
    {
        $accountId = trim($accountId);
        $formatError = $this->validateAccountIdFormat($accountId);

        if ($formatError !== null) {
            return [
                'supported' => true,
                'valid' => false,
                'player_id' => null,
                'username' => null,
                'message' => $formatError,
            ];
        }

        $mapping = $this->getMapping($product);
        $supplier = $mapping?->supplierApi;

        if ($mapping === null || $supplier === null) {
            return [
                'supported' => false,
                'valid' => true,
                'player_id' => null,
                'username' => null,
                'message' => null,
            ];
        }

        if (! $this->supplierSupportsPlayerIdValidation($supplier)) {
            return [
                'supported' => false,
                'valid' => true,
                'player_id' => null,
                'username' => null,
                'message' => null,
            ];
        }

        $driver = $this->resolveGolfPlayerValidationDriver($supplier);

        if ($driver === null) {
            return [
                'supported' => false,
                'valid' => true,
                'player_id' => null,
                'username' => null,
                'message' => null,
            ];
        }

        if (! $driver->requiresJawakerPlayerValidation((string) $mapping->supplier_product_id)) {
            return [
                'supported' => false,
                'valid' => true,
                'player_id' => null,
                'username' => null,
                'message' => null,
            ];
        }

        $result = $driver->validatePlayerId($accountId);

        if ($result['valid']) {
            $username = $result['username'] ?? null;

            return [
                'supported' => true,
                'valid' => true,
                'player_id' => $result['playerId'] ?? $accountId,
                'username' => is_string($username) ? $username : null,
                'message' => $username !== null && $username !== ''
                    ? translate('direct_topup_account_verified').': '.$username
                    : translate('direct_topup_account_verified'),
            ];
        }

        return [
            'supported' => true,
            'valid' => false,
            'player_id' => null,
            'username' => null,
            'message' => (string) ($result['error'] ?? translate('direct_topup_account_invalid')),
        ];
    }

    /**
     * @return array{payload: array<string, mixed>, status: int}
     */
    public function buildAccountValidationResponse(Product $product, string $accountId): array
    {
        if (! $this->isDirectTopUpProduct($product)) {
            return [
                'payload' => [
                    'supported' => false,
                    'valid' => false,
                    'message' => translate('product_is_not_direct_topup'),
                ],
                'status' => 400,
            ];
        }

        $result = $this->validateAccountWithSupplier($product, $accountId);

        if (! $result['supported']) {
            return [
                'payload' => [
                    'supported' => false,
                    'valid' => true,
                    'player_id' => null,
                    'username' => null,
                    'message' => null,
                ],
                'status' => 200,
            ];
        }

        if ($result['valid']) {
            return [
                'payload' => [
                    'supported' => true,
                    'valid' => true,
                    'player_id' => $result['player_id'],
                    'username' => $result['username'],
                    'message' => $result['message'],
                ],
                'status' => 200,
            ];
        }

        return [
            'payload' => [
                'supported' => true,
                'valid' => false,
                'player_id' => null,
                'username' => null,
                'message' => $result['message'] ?? translate('direct_topup_account_invalid'),
            ],
            'status' => 422,
        ];
    }

    private function supplierSupportsPlayerIdValidation(?SupplierApi $supplier): bool
    {
        if ($supplier === null || ! $supplier->supports_direct_top_up) {
            return false;
        }

        if ($supplier->driver === 'golf_api') {
            return true;
        }

        if ($supplier->driver !== 'generic_rest') {
            return false;
        }

        $settings = $supplier->settings ?? [];

        return ! empty($settings['topup_use_product_custom_fields']);
    }

    private function resolveGolfPlayerValidationDriver(SupplierApi $supplier): ?GolfApiDriver
    {
        if (! $this->supplierSupportsPlayerIdValidation($supplier)) {
            return null;
        }

        if ($supplier->driver === 'golf_api') {
            $driver = $this->supplierManager->driver($supplier);

            return $driver instanceof GolfApiDriver ? $driver : null;
        }

        return app(GolfApiDriver::class)->configure($supplier);
    }

    private function validateAccountIdFormat(string $accountId): ?string
    {
        if ($accountId === '') {
            return translate('direct_topup_account_id_is_required');
        }

        if (strlen($accountId) > 255) {
            return translate('direct_topup_account_id_too_long');
        }

        if (! preg_match('/^[a-zA-Z0-9_\-\.@]+$/u', $accountId)) {
            return translate('direct_topup_account_id_invalid_format');
        }

        return null;
    }

    public function getPricePerUnit(Product $product): float
    {
        return $product->getEffectiveSellPrice();
    }

    public function calculateTotalPrice(Product $product, float $quantity): float
    {
        $this->validateConfiguration($product);

        return round($quantity * $this->getPricePerUnit($product), 2);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buildModalConfig(Product $product): ?array
    {
        if (! $this->isDirectTopUpProduct($product)) {
            return null;
        }

        $mapping = $this->getMapping($product);

        if ($mapping === null) {
            return null;
        }

        return [
            'enabled' => true,
            'account_label' => trim((string) ($mapping->direct_topup_account_label ?? '')) !== ''
                ? (string) $mapping->direct_topup_account_label
                : (translate('account_id') ?: 'Account ID'),
            'requires_account_verification' => $this->requiresAccountVerification($product),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buildApiPayload(Product $product): ?array
    {
        if (! $this->isDirectTopUpProduct($product)) {
            return null;
        }

        return $this->buildModalConfig($product);
    }

    public function sanitizeAccountIdForLogging(string $accountId): string
    {
        $accountId = trim($accountId);

        if (strlen($accountId) <= 4) {
            return '****';
        }

        return substr($accountId, 0, 2).str_repeat('*', max(0, strlen($accountId) - 4)).substr($accountId, -2);
    }

    private function getMapping(Product $product): ?SupplierProductMapping
    {
        if ($product->relationLoaded('supplierMapping')) {
            return $product->supplierMapping;
        }

        if ($product->relationLoaded('mapping')) {
            return $product->mapping;
        }

        return SupplierProductMapping::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->with('supplierApi')
            ->first();
    }
}
