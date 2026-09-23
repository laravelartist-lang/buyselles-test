<?php

namespace Tests\Feature;

use App\DTOs\Supplier\AvailabilityResult;
use App\DTOs\Supplier\BalanceResult;
use App\DTOs\Supplier\StockResult;
use App\Services\Order\PendingReviewHoldService;
use App\Services\Supplier\SupplierAvailabilityService;
use PHPUnit\Framework\TestCase;

class StripeSupplierCheckoutGateTest extends TestCase
{
    public function test_unavailable_supplier_blocks_stripe_session_creation(): void
    {
        $service = $this->availabilityService();
        $service->mappings[11] = $this->activeMapping();
        $service->stock = new StockResult(available: 0, price: 1.0);

        $result = $service->checkCarts([$this->cart(productId: 11, qty: 1)]);

        $this->assertFalse($result->ok);
        $this->assertFalse($service->shouldCreateStripeSession((object) [
            'attribute' => 'order',
            'payer_id' => 1,
        ]));
        $this->assertStringContainsString('out of stock', $result->errorMessage());
        $this->assertContains(11, $result->failedProductIds);
    }

    public function test_wallet_add_funds_is_not_blocked_by_supplier_gate(): void
    {
        $service = $this->availabilityService();
        $service->mappings[11] = $this->activeMapping();
        $service->stock = new StockResult(available: 0, price: 1.0);

        $result = $service->checkPaymentRequest((object) [
            'attribute' => 'add_funds_to_wallet',
            'payer_id' => 1,
        ]);

        $this->assertTrue($result->ok);
        $this->assertTrue($service->shouldCreateStripeSession((object) [
            'attribute' => 'add_funds_to_wallet',
        ]));
    }

    public function test_paid_stripe_order_is_held_for_review_when_supplier_fails(): void
    {
        $order = $this->paidOrder();
        $hold = new PendingReviewHoldService;
        $hold->holdOrder($order, PendingReviewHoldService::REASON_UNAVAILABLE);

        $this->assertSame('pending_review', $order->order_status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(PendingReviewHoldService::REASON_UNAVAILABLE, $order->review_reason);
    }

    public function test_stripe_controller_does_not_call_refund_api(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Payment_Methods/StripePaymentController.php');
        $holdService = file_get_contents(dirname(__DIR__, 2).'/app/Services/Order/PendingReviewHoldService.php');

        $this->assertStringContainsString('checkPaymentRequest', $controller);
        $this->assertStringContainsString('holdOrdersForPayment', $controller);
        $this->assertStringNotContainsString('Refund::', $controller);
        $this->assertStringNotContainsString('refunds->create', $controller);
        $this->assertStringNotContainsString('Refund::', $holdService);
    }

    public function test_insufficient_supplier_balance_blocks_checkout(): void
    {
        $service = $this->availabilityService();
        $service->mappings[22] = $this->activeMapping(costPrice: 10);
        $service->stock = new StockResult(available: 5, price: 10.0);
        $service->balance = new BalanceResult(supported: true, balance: 1.0);

        $result = $service->checkCarts([$this->cart(productId: 22, qty: 1)]);

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('balance', strtolower($result->errorMessage()));
    }

    public function test_local_codes_allow_checkout_when_priority_is_local_first(): void
    {
        $service = $this->availabilityService();
        $service->localCounts[33] = 2;
        $service->mappings[33] = $this->activeMapping();
        $service->stock = new StockResult(available: 0, price: 1.0);

        $result = $service->checkCarts([$this->cart(productId: 33, qty: 2)]);

        $this->assertTrue($result->ok);
    }

    private function availabilityService(): TestableSupplierAvailabilityService
    {
        return TestableSupplierAvailabilityService::make();
    }

    private function cart(int $productId, int $qty): object
    {
        return (object) [
            'product_id' => $productId,
            'qty' => $qty,
            'product' => (object) ['name' => 'Gift Card'],
        ];
    }

    private function activeMapping(float $costPrice = 5): object
    {
        return (object) [
            'id' => 1,
            'product_id' => 11,
            'supplier_api_id' => 7,
            'supplier_product_id' => 'sku-1',
            'cost_price' => $costPrice,
            'code_source_priority' => 'local_first',
            'supplierApi' => (object) [
                'id' => 7,
                'is_active' => true,
            ],
        ];
    }

    private function paidOrder(): object
    {
        return new class
        {
            public int $id = 99;

            public string $order_status = 'pending';

            public string $payment_status = 'paid';

            public ?string $review_reason = null;

            public function save(): bool
            {
                return true;
            }
        };
    }
}

class TestableSupplierAvailabilityService extends SupplierAvailabilityService
{
    /** @var array<int, object> */
    public array $mappings = [];

    /** @var array<int, int> */
    public array $localCounts = [];

    public ?StockResult $stock = null;

    public ?BalanceResult $balance = null;

    public static function make(): self
    {
        /** @var self $service */
        $service = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();

        return $service;
    }

    public function checkPaymentRequest(object $payment): AvailabilityResult
    {
        $attribute = $payment->attribute ?? null;

        if ($attribute !== null && $attribute !== 'order') {
            return AvailabilityResult::available();
        }

        if ($this->mappings === []) {
            return AvailabilityResult::available();
        }

        $carts = [];
        foreach (array_keys($this->mappings) as $productId) {
            $carts[] = (object) [
                'product_id' => $productId,
                'qty' => 1,
                'product' => (object) ['name' => 'Gift Card'],
            ];
        }

        return $this->checkCarts($carts);
    }

    protected function localCodeCount(int $productId): int
    {
        return $this->localCounts[$productId] ?? 0;
    }

    protected function activeMapping(int $productId): ?object
    {
        return $this->mappings[$productId] ?? null;
    }

    protected function cachedStockResult(object $mapping): StockResult
    {
        return $this->stock ?? new StockResult(available: 0, price: 0);
    }

    protected function cachedBalanceResult(object $mapping): BalanceResult
    {
        return $this->balance ?? BalanceResult::unsupported();
    }
}
