<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_direct_topup')->default(false)->after('digital_product_type');
            $table->string('direct_topup_account_label', 255)->nullable()->after('is_direct_topup');
            $table->decimal('direct_topup_min_quantity', 20, 4)->nullable()->after('direct_topup_account_label');
            $table->decimal('direct_topup_max_quantity', 20, 4)->nullable()->after('direct_topup_min_quantity');
            $table->decimal('direct_topup_price_per_unit', 24, 8)->nullable()->after('direct_topup_max_quantity');
        });

        Schema::table('carts', function (Blueprint $table): void {
            $table->text('direct_topup_account_id')->nullable()->after('custom_amount');
            $table->decimal('direct_topup_quantity', 20, 4)->nullable()->after('direct_topup_account_id');
        });

        Schema::table('order_details', function (Blueprint $table): void {
            $table->text('direct_topup_account_id')->nullable()->after('custom_amount');
            $table->decimal('direct_topup_quantity', 20, 4)->nullable()->after('direct_topup_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table): void {
            $table->dropColumn(['direct_topup_account_id', 'direct_topup_quantity']);
        });

        Schema::table('carts', function (Blueprint $table): void {
            $table->dropColumn(['direct_topup_account_id', 'direct_topup_quantity']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'is_direct_topup',
                'direct_topup_account_label',
                'direct_topup_min_quantity',
                'direct_topup_max_quantity',
                'direct_topup_price_per_unit',
            ]);
        });
    }
};
