<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CustomerCheckoutIdempotency;
use App\Services\Order\CustomerCheckoutGuardService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class CustomerWalletCheckoutIdempotencyTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recreateTable('customer_checkout_idempotency', function (Blueprint $table): void {
            $table->id();
            $table->string('customer_scope', 64);
            $table->string('checkout_method', 32);
            $table->string('idempotency_key', 128)->nullable();
            $table->string('cart_fingerprint', 64);
            $table->string('status', 16);
            $table->json('order_ids')->nullable();
            $table->json('response_payload')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['customer_scope', 'idempotency_key'], 'customer_checkout_idempotency_key_unique');
            $table->index(['customer_scope', 'cart_fingerprint', 'checkout_method', 'created_at'], 'customer_checkout_fingerprint_idx');
        });
    }

    public function test_duplicate_idempotency_key_replays_without_second_processor_run(): void
    {
        $guard = app(CustomerCheckoutGuardService::class);
        $request = Request::create('/api/v1/customer/order/place-by-wallet', 'POST', [
            'idempotency_key' => 'wallet-test-key-001',
        ]);
        $request->setUserResolver(static fn () => (object) ['id' => 42]);

        $carts = new Collection([
            new Cart([
                'id' => 1,
                'product_id' => 10,
                'quantity' => 1,
                'cart_group_id' => 'group-1',
            ]),
        ]);

        $runs = 0;
        $processor = static function () use (&$runs): array {
            $runs++;

            return [
                'http_status' => 200,
                'payload' => [
                    'message' => 'ok',
                    'order_ids' => [9001],
                ],
            ];
        };

        $first = $guard->executeWalletCheckout($request, $carts, $processor);
        $second = $guard->executeWalletCheckout($request, $carts, $processor);

        $this->assertSame(1, $runs);
        $this->assertSame(200, $first['http_status']);
        $this->assertSame(200, $second['http_status']);
        $this->assertTrue($second['idempotent_replay'] ?? false);
        $this->assertSame([9001], $second['payload']['order_ids']);
        $this->assertSame(1, CustomerCheckoutIdempotency::query()->count());
    }

    public function test_same_cart_fingerprint_replays_recent_completed_checkout(): void
    {
        $guard = app(CustomerCheckoutGuardService::class);
        $carts = new Collection([
            new Cart([
                'id' => 5,
                'product_id' => 20,
                'quantity' => 2,
                'cart_group_id' => 'group-9',
            ]),
        ]);

        $fingerprint = $guard->buildCartFingerprint($carts);

        CustomerCheckoutIdempotency::query()->create([
            'customer_scope' => 'user:7',
            'checkout_method' => 'wallet',
            'idempotency_key' => 'first-attempt',
            'cart_fingerprint' => $fingerprint,
            'status' => CustomerCheckoutIdempotency::STATUS_COMPLETED,
            'order_ids' => [501],
            'response_payload' => [
                'message' => 'already ordered',
                'order_ids' => [501],
            ],
            'http_status' => 200,
        ]);

        $request = Request::create('/checkout-complete-wallet', 'POST');
        $request->setUserResolver(static fn () => (object) ['id' => 7]);

        $runs = 0;
        $result = $guard->executeWalletCheckout($request, $carts, static function () use (&$runs): array {
            $runs++;

            return [
                'http_status' => 200,
                'payload' => ['order_ids' => [999]],
            ];
        });

        $this->assertSame(0, $runs);
        $this->assertTrue($result['idempotent_replay'] ?? false);
        $this->assertSame([501], $result['payload']['order_ids']);
    }

    public function test_duplicate_checkout_processor_runs_once_so_order_placed_side_effects_are_not_doubled(): void
    {
        $guard = app(CustomerCheckoutGuardService::class);
        $request = Request::create('/api/v1/customer/order/place-by-wallet', 'POST', [
            'idempotency_key' => 'wallet-email-dedup-key',
        ]);
        $request->setUserResolver(static fn () => (object) ['id' => 55]);

        $carts = new Collection([
            new Cart([
                'id' => 2,
                'product_id' => 11,
                'quantity' => 1,
                'cart_group_id' => 'group-2',
            ]),
        ]);

        $orderPlacedDispatches = 0;
        $processor = static function () use (&$orderPlacedDispatches): array {
            $orderPlacedDispatches++;

            return [
                'http_status' => 200,
                'payload' => [
                    'message' => 'ok',
                    'order_ids' => [9100],
                ],
            ];
        };

        $guard->executeWalletCheckout($request, $carts, $processor);
        $guard->executeWalletCheckout($request, $carts, $processor);

        $this->assertSame(1, $orderPlacedDispatches);
    }
}
