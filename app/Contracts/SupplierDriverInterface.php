<?php

namespace App\Contracts;

use App\DTOs\Supplier\BalanceResult;
use App\DTOs\Supplier\HealthResult;
use App\DTOs\Supplier\StockResult;
use App\DTOs\Supplier\SupplierOrderResult;
use App\DTOs\Supplier\SupplierProductDTO;
use App\DTOs\Supplier\WebhookResult;
use App\Models\SupplierApi;
use Illuminate\Http\Request;

interface SupplierDriverInterface
{
    /**
     * Boot the driver with the given supplier configuration.
     */
    public function configure(SupplierApi $supplier): static;

    /**
     * Authenticate with the supplier API. Returns true on success.
     */
    public function authenticate(): bool;

    /**
     * Fetch the product catalog from the supplier.
     *
     * @param  array<string, mixed>  $filters
     * @return SupplierProductDTO[]
     */
    public function fetchProducts(array $filters = []): array;

    /**
     * Check stock availability for a specific supplier product.
     */
    public function fetchStock(string $supplierProductId): StockResult;

    /**
     * Place an order with the supplier for the given product and quantity.
     *
     * @param  float|null  $unitPrice  Optional cost/face-value per unit (from product mapping).
     */
    public function placeOrder(string $supplierProductId, int $quantity, ?float $unitPrice = null): SupplierOrderResult;

    /**
     * Place a direct top-up order (account ID + quantity, no code inventory).
     */
    public function placeTopUpOrder(
        string $supplierProductId,
        float $quantity,
        string $accountId,
        ?float $unitPrice = null,
    ): SupplierOrderResult;

    /**
     * Get the current status of a supplier order.
     */
    public function getOrderStatus(string $supplierOrderId): SupplierOrderResult;

    /**
     * Parse and verify an incoming webhook from this supplier.
     */
    public function parseWebhook(Request $request): WebhookResult;

    /**
     * Perform a health check against the supplier API.
     */
    public function healthCheck(): HealthResult;

    /**
     * Retrieve the current account balance / available credit from the supplier.
     * Drivers that do not support balance queries must return BalanceResult::unsupported().
     */
    public function getBalance(): BalanceResult;

    /**
     * Return the list of credential fields required by this driver.
     * Used by admin UI to render dynamic forms.
     *
     * @return array<string, array{label: string, type: string, required: bool}>
     */
    public function getRequiredCredentialFields(): array;

    /**
     * Return driver-specific settings schema for admin UI.
     *
     * @return array<string, array{label: string, type: string, default: mixed}>
     */
    public function getConfigSchema(): array;
}
