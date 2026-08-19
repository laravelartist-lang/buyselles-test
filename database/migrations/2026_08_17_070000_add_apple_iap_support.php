<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'apple_product_id')) {
                $table->string('apple_product_id', 191)->nullable()->after('code');
            }
        });

        if (! Schema::hasTable('iap_transactions')) {
            Schema::create('iap_transactions', function (Blueprint $table) {
                $table->id();
                $table->string('transaction_id')->unique();
                $table->string('apple_product_id');
                $table->unsignedBigInteger('product_id')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->unsignedBigInteger('order_id')->nullable();
                $table->string('environment', 32)->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('business_settings')) {
            \Illuminate\Support\Facades\DB::table('business_settings')->updateOrInsert(
                ['type' => 'ios_iap_status'],
                ['value' => '1', 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'apple_product_id')) {
                $table->dropColumn('apple_product_id');
            }
        });

        Schema::dropIfExists('iap_transactions');
    }
};
