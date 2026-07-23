<?php

namespace Tests\Feature;

use App\Events\OrderPlacedEvent;
use App\Listeners\OrderPlacedListener;
use App\Models\EmailTemplate;
use App\Models\Order;
use App\Services\Order\OrderPlacedEmailService;
use App\Utils\OrderManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class OrderPlacedEmailDedupTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recreateTable('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('order_status')->default('confirmed');
            $table->timestamp('order_placed_email_sent_at')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('email_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('template_name');
            $table->string('user_type');
            $table->string('template_design_name')->nullable();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });

        $this->recreateTable('translations', function (Blueprint $table): void {
            $table->id();
            $table->string('translationable_type');
            $table->unsignedBigInteger('translationable_id');
            $table->string('locale')->nullable();
            $table->string('key')->nullable();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('social_medias', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('link')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });

        $this->recreateTable('business_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->text('value')->nullable();
        });

        $this->app['db']->table('business_settings')->insert([
            'type' => 'mail_config',
            'value' => json_encode(['status' => 1, 'name' => 'smtp']),
        ]);

        EmailTemplate::query()->create([
            'template_name' => 'order-place',
            'user_type' => 'customer',
            'template_design_name' => 'default',
            'title' => 'Order placed',
            'body' => 'Hello {userName}',
            'status' => 1,
        ]);
    }

    public function test_reserve_send_allows_only_one_customer_order_placed_email(): void
    {
        $order = Order::query()->create([
            'customer_id' => 1,
            'order_status' => 'delivered',
        ]);

        $service = app(OrderPlacedEmailService::class);

        $this->assertTrue($service->reserveSend($order->id));
        $this->assertFalse($service->reserveSend($order->id));
        $this->assertTrue($service->hasBeenSent($order->id));
    }

    public function test_listener_skips_already_sent_customer_order_place_mail(): void
    {
        $order = Order::query()->create([
            'customer_id' => 1,
            'order_status' => 'delivered',
        ]);

        app(OrderPlacedEmailService::class)->reserveSend($order->id);

        $listener = app(OrderPlacedListener::class);
        Mail::fake();

        $listener->handle(new OrderPlacedEvent(
            email: 'customer@example.com',
            data: [
                'subject' => 'Order placed',
                'title' => 'Order placed',
                'userName' => 'Test',
                'userType' => 'customer',
                'templateName' => 'order-place',
                'orderId' => $order->id,
            ],
        ));

        Mail::assertNothingQueued();
    }

    public function test_complete_deferred_checkout_is_idempotent(): void
    {
        Event::fake([OrderPlacedEvent::class]);

        $order = Order::query()->create([
            'customer_id' => 1,
            'order_status' => 'delivered',
        ]);

        session([
            'deferred_checkout_completion' => [
                'notification_events' => [],
                'mail_events' => [[[
                    'email' => 'customer@example.com',
                    'data' => [
                        'subject' => 'Order placed',
                        'title' => 'Order placed',
                        'userName' => 'Test',
                        'userType' => 'customer',
                        'templateName' => 'order-place',
                        'orderId' => $order->id,
                    ],
                ]]],
                'cart_group_ids' => [],
                'referral_user_id' => null,
            ],
        ]);

        OrderManager::completeDeferredCheckout();
        OrderManager::completeDeferredCheckout();

        Event::assertDispatched(OrderPlacedEvent::class, 1);
        $this->assertNull(session('deferred_checkout_completion'));
    }
}
