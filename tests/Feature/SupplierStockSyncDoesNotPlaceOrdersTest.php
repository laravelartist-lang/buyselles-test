<?php

namespace Tests\Feature;

use App\Enums\SupplierOrderPlacementIntent;
use App\Services\Supplier\SupplierManager;
use ReflectionMethod;
use Tests\TestCase;

class SupplierStockSyncDoesNotPlaceOrdersTest extends TestCase
{
    public function test_sync_stock_method_does_not_call_place_supplier_order(): void
    {
        $method = new ReflectionMethod(SupplierManager::class, 'syncStock');
        $source = file_get_contents($method->getFileName()) ?: '';
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $lines = explode("\n", $source);
        $methodBody = implode("\n", array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringNotContainsString('placeSupplierOrder', $methodBody);
        $this->assertStringNotContainsString('auto_restock', $methodBody);
    }

    public function test_supplier_order_placement_intent_only_allows_customer_fulfillment(): void
    {
        $cases = SupplierOrderPlacementIntent::cases();

        $this->assertCount(1, $cases);
        $this->assertSame(SupplierOrderPlacementIntent::CustomerFulfillment, $cases[0]);
    }
}
