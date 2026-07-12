<?php

namespace Tests\Concerns;

use App\Models\ResellerApiKey;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;

trait SetsUpPartnerApiTestSchema
{
    protected string $rawApiKey = 'rslr_test_partner_api_key_1234567890';

    protected string $rawApiSecret = 'test_partner_api_secret_abcdefghijklmnop';

    protected function setUpPartnerApiSchema(): void
    {
        $this->recreateTable('business_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->text('value')->nullable();
        });

        $this->app['db']->table('business_settings')->insert([
            [
                'type' => 'language',
                'value' => json_encode([['code' => 'en', 'default' => true, 'direction' => 'ltr']]),
            ],
            [
                'type' => 'company_name',
                'value' => 'Test Shop',
            ],
            [
                'type' => 'wallet_status',
                'value' => '1',
            ],
        ]);

        $this->recreateTable('translations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('translationable_id')->nullable();
            $table->string('translationable_type')->nullable();
            $table->string('locale')->nullable();
            $table->string('key')->nullable();
            $table->text('value')->nullable();
        });

        $this->recreateTable('reviews', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('delivery_man_id')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        $this->recreateTable('storages', function (Blueprint $table): void {
            $table->id();
            $table->string('data_type')->nullable();
            $table->unsignedBigInteger('data_id')->nullable();
            $table->string('key')->nullable();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('users', function (Blueprint $table): void {
            $table->id();
            $table->string('f_name')->nullable();
            $table->string('email')->nullable();
            $table->decimal('wallet_balance', 24, 4)->default(0);
            $table->timestamps();
        });

        $this->recreateTable('customer_wallets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->decimal('balance', 24, 4)->default(0);
            $table->timestamps();
        });

        $this->recreateTable('wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->uuid('transaction_id')->nullable();
            $table->string('reference')->nullable();
            $table->string('transaction_type')->nullable();
            $table->string('payment_method')->nullable();
            $table->decimal('credit', 24, 4)->default(0);
            $table->decimal('debit', 24, 4)->default(0);
            $table->decimal('balance', 24, 4)->default(0);
            $table->json('order_ids')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('sellers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('seller_wallets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('seller_id')->index();
            $table->decimal('total_earning', 24, 4)->default(0);
            $table->decimal('pending_balance', 24, 4)->default(0);
            $table->decimal('pending_withdraw', 24, 4)->default(0);
            $table->timestamps();
        });

        $this->recreateTable('reseller_api_keys', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->string('name');
            $table->string('api_key', 64)->unique();
            $table->string('api_secret', 255);
            $table->json('allowed_ips')->nullable();
            $table->unsignedInteger('rate_limit_per_minute')->default(60);
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('active');
            $table->json('permissions')->nullable();
            $table->decimal('wallet_balance', 24, 4)->default(0);
            $table->unsignedBigInteger('total_requests')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->timestamps();
        });

        $this->recreateTable('products', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->default(1);
            $table->string('added_by')->default('admin');
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('product_type')->default('digital');
            $table->string('digital_product_type')->default('ready_product');
            $table->integer('status')->default(1);
            $table->integer('request_status')->default(1);
            $table->boolean('partner_approved')->default(false);
            $table->decimal('unit_price', 24, 4)->default(0);
            $table->decimal('purchase_price', 24, 4)->default(0);
            $table->integer('current_stock')->default(0);
            $table->text('details')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('supplier_apis', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('driver')->nullable();
            $table->string('base_url')->nullable();
            $table->text('credentials')->nullable();
            $table->string('auth_type')->default('api_key');
            $table->json('settings')->nullable();
            $table->integer('rate_limit_per_minute')->default(60);
            $table->boolean('is_active')->default(true);
            $table->boolean('supports_direct_top_up')->default(false);
            $table->string('health_status')->default('healthy');
            $table->timestamps();
        });

        $this->recreateTable('supplier_product_mappings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('supplier_api_id')->nullable();
            $table->string('supplier_product_id')->nullable();
            $table->integer('priority')->default(1);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_direct_topup')->default(false);
            $table->timestamps();
        });

        $this->recreateTable('digital_product_codes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->text('code')->nullable();
            $table->string('code_hash')->nullable();
            $table->string('serial_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('status')->default('available')->index();
            $table->string('source')->default('manual');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('order_detail_id')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('customer_type')->nullable();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->default('paid');
            $table->string('order_status')->default('processing');
            $table->decimal('order_amount', 24, 4)->default(0);
            $table->string('order_type')->default('default');
            $table->text('order_note')->nullable();
            $table->integer('is_guest')->default(0);
            $table->timestamps();
        });

        $this->recreateTable('order_details', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->integer('qty')->default(1);
            $table->decimal('price', 24, 4)->default(0);
            $table->decimal('tax', 24, 4)->default(0);
            $table->decimal('discount', 24, 4)->default(0);
            $table->text('product_details')->nullable();
            $table->string('product_type')->nullable();
            $table->string('digital_product_type')->nullable();
            $table->string('payment_status')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('partner_order_idempotency', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('reseller_api_key_id');
            $table->string('idempotency_key');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->json('response_payload');
            $table->timestamps();
        });

        $this->recreateTable('partner_api_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('reseller_api_key_id')->nullable();
            $table->string('method')->nullable();
            $table->string('endpoint')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->string('ip_address')->nullable();
            $table->json('request_summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    protected function seedActivePartnerApiKey(int $sellerId = 1, float $walletBalance = 1000): ResellerApiKey
    {
        $userId = $this->app['db']->table('users')->insertGetId([
            'f_name' => 'Partner',
            'email' => 'partner@test.com',
            'wallet_balance' => $walletBalance,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ResellerApiKey::query()->create([
            'user_id' => $userId,
            'seller_id' => null,
            'name' => 'Test Partner Key',
            'api_key' => hash('sha256', $this->rawApiKey),
            'api_secret' => hash('sha256', $this->rawApiSecret),
            'permissions' => ['products.list', 'orders.create', 'orders.view', 'balance.view'],
            'rate_limit_per_minute' => 60,
            'is_active' => true,
            'status' => 'active',
            'wallet_balance' => 0,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function partnerApiHeaders(): array
    {
        return [
            'X-API-KEY' => $this->rawApiKey,
            'X-API-SECRET' => $this->rawApiSecret,
            'Accept' => 'application/json',
        ];
    }

    protected function seedDigitalCode(int $productId, string $plainCode = 'TEST-CODE-001'): void
    {
        $this->app['db']->table('digital_product_codes')->insert([
            'product_id' => $productId,
            'code' => Crypt::encryptString($plainCode),
            'code_hash' => hash('sha256', strtolower(trim($plainCode))),
            'status' => 'available',
            'source' => 'manual',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
