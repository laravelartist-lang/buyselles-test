<?php

namespace Tests\Feature;

use App\Jobs\DirectTopUpFulfillmentJob;
use App\Jobs\SupplierCodeFetchJob;
use App\Models\Order;
use App\Models\User;
use App\Services\DirectTopUp\DirectTopUpWalletCheckoutService;
use App\Services\Supplier\SupplierOrderEligibilityService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class FulfillmentFailureNoteTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recreateTable('users', function (Blueprint $table): void {
            $table->id();
            $table->string('f_name')->nullable();
            $table->string('email')->nullable();
            $table->decimal('wallet_balance', 24, 4)->default(0);
            $table->timestamps();
        });

        $this->recreateTable('business_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->text('value')->nullable();
        });

        $this->app['db']->table('business_settings')->insert([
            ['type' => 'wallet_status', 'value' => '1'],
            ['type' => 'language', 'value' => json_encode([['code' => 'en', 'default' => true]])],
        ]);

        $this->recreateTable('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->default('paid');
            $table->string('order_status')->default('confirmed');
            $table->decimal('order_amount', 24, 4)->default(0);
            $table->text('order_note')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('order_details', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('delivery_status')->nullable();
            $table->string('payment_status')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('order_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_type')->nullable();
            $table->string('status')->nullable();
            $table->string('cause')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->uuid('transaction_id')->nullable();
            $table->string('reference')->nullable();
            $table->string('transaction_type')->nullable();
            $table->decimal('credit', 24, 4)->default(0);
            $table->decimal('debit', 24, 4)->default(0);
            $table->decimal('balance', 24, 4)->default(0);
            $table->json('order_ids')->nullable();
            $table->timestamps();
        });
    }

    public function test_direct_topup_mark_failed_stores_plain_note_from_truncated_supplier_json(): void
    {
        $user = User::query()->create([
            'f_name' => 'Test',
            'email' => 'note@test.com',
            'wallet_balance' => 50,
        ]);

        $order = Order::query()->create([
            'customer_id' => $user->id,
            'payment_method' => 'pay_by_wallet',
            'payment_status' => 'paid',
            'order_status' => 'processing',
            'order_amount' => 10,
        ]);

        $rawError = "HTTP request returned status code 400:\n"
            .'{"message":"Account does not have enough funds. Balance: 0.36. Reserved balanc (truncated...)';

        app(DirectTopUpWalletCheckoutService::class)->markDirectTopUpOrderFailed(
            $order,
            $rawError,
            $user->id,
        );

        $order->refresh();

        $this->assertSame('failed', $order->order_status);
        $this->assertStringContainsString('enough funds', (string) $order->order_note);
        $this->assertStringNotContainsString('"message"', (string) $order->order_note);
        $this->assertStringNotContainsString('{', (string) $order->order_note);
    }

    public function test_format_fulfillment_error_strips_product_prefix_and_json(): void
    {
        $service = app(DirectTopUpWalletCheckoutService::class);

        $plain = $service->formatFulfillmentError(
            "Product 'PUBG UC': Supplier error: HTTP request returned status code 400:\n"
            .'{"message":"Top-up rejected by supplier (truncated...)'
        );

        $this->assertStringContainsString('Top-up rejected', $plain);
        $this->assertStringNotContainsString('"message"', $plain);
    }

    public function test_supplier_code_fetch_job_failed_stores_plain_order_note(): void
    {
        $user = User::query()->create([
            'f_name' => 'Test',
            'email' => 'fetch-fail@test.com',
            'wallet_balance' => 100,
        ]);

        $order = Order::query()->create([
            'customer_id' => $user->id,
            'payment_method' => 'pay_by_wallet',
            'payment_status' => 'paid',
            'order_status' => 'confirmed',
            'order_amount' => 15,
        ]);

        $this->app['db']->table('wallet_transactions')->insert([
            'user_id' => $user->id,
            'transaction_id' => (string) \Str::uuid(),
            'reference' => 'order payment',
            'transaction_type' => 'order_place',
            'debit' => 15,
            'credit' => 0,
            'balance' => 85,
            'order_ids' => json_encode([$order->id]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $job = new SupplierCodeFetchJob($order->id);
        $job->failed($this->supplierRequestException('Supplier balance too low for purchase.'));

        $order->refresh();

        $this->assertSame('failed', $order->order_status);
        $this->assertStringContainsString('Supplier balance too low', (string) $order->order_note);
        $this->assertStringNotContainsString('"message"', (string) $order->order_note);
    }

    public function test_direct_topup_fulfillment_job_failed_stores_plain_order_note(): void
    {
        $user = User::query()->create([
            'f_name' => 'Test',
            'email' => 'dtu-fail@test.com',
            'wallet_balance' => 80,
        ]);

        $order = Order::query()->create([
            'customer_id' => $user->id,
            'payment_method' => 'pay_by_wallet',
            'payment_status' => 'paid',
            'order_status' => 'confirmed',
            'order_amount' => 20,
        ]);

        $this->mock(SupplierOrderEligibilityService::class, function ($mock): void {
            $mock->shouldReceive('orderHasTerminalFulfillmentStatus')->andReturn(false);
        });

        $job = new DirectTopUpFulfillmentJob($order->id);
        $job->failed($this->supplierRequestException('Player ID could not be validated.'));

        $order->refresh();

        $this->assertSame('failed', $order->order_status);
        $this->assertStringContainsString('Player ID could not be validated', (string) $order->order_note);
        $this->assertStringNotContainsString('"message"', (string) $order->order_note);
    }

    private function supplierRequestException(string $message): RequestException
    {
        $response = new Response(
            new \GuzzleHttp\Psr7\Response(
                400,
                ['Content-Type' => 'application/json'],
                json_encode(['message' => $message], JSON_THROW_ON_ERROR)
            )
        );

        return new RequestException($response);
    }
}
