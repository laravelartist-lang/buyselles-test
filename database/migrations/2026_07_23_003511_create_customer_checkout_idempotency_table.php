<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_checkout_idempotency', function (Blueprint $table): void {
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

    public function down(): void
    {
        Schema::dropIfExists('customer_checkout_idempotency');
    }
};
