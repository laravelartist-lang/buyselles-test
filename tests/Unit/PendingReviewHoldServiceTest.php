<?php

namespace Tests\Unit;

use App\Services\Order\PendingReviewHoldService;
use App\Services\Supplier\SupplierOrderEligibilityService;
use PHPUnit\Framework\TestCase;

class PendingReviewHoldServiceTest extends TestCase
{
    public function test_hold_marks_paid_order_pending_review_without_refunding(): void
    {
        $order = $this->fakeOrder();
        $service = new PendingReviewHoldService;

        $service->holdOrder($order, PendingReviewHoldService::REASON_UNAVAILABLE, 'supplier empty');

        $this->assertSame(PendingReviewHoldService::STATUS, $order->order_status);
        $this->assertSame(PendingReviewHoldService::REASON_UNAVAILABLE, $order->review_reason);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(1, $order->saveCount);
        $this->assertFalse(method_exists($service, 'refund'));
    }

    public function test_hold_is_idempotent(): void
    {
        $order = $this->fakeOrder();
        $service = new PendingReviewHoldService;

        $service->holdForFulfillmentFailure($order, 'first failure');
        $service->holdForFulfillmentFailure($order, 'second failure');

        $this->assertSame(PendingReviewHoldService::REASON_API_FAILED, $order->review_reason);
        $this->assertSame(1, $order->saveCount);
    }

    public function test_pending_review_is_not_retryable(): void
    {
        $order = $this->fakeOrder();
        $order->order_status = PendingReviewHoldService::STATUS;

        $eligibility = new SupplierOrderEligibilityService;

        $this->assertTrue($eligibility->orderHasTerminalFulfillmentStatus($order));
        $this->assertTrue((new PendingReviewHoldService)->isNonRetryable($order));
    }

    private function fakeOrder(): object
    {
        return new class
        {
            public int $id = 42;

            public string $order_status = 'pending';

            public string $payment_status = 'paid';

            public ?string $review_reason = null;

            public string $order_note = '';

            public int $saveCount = 0;

            public function save(): bool
            {
                $this->saveCount++;

                return true;
            }
        };
    }
}
