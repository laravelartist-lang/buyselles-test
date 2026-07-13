<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_product_mappings', function (Blueprint $table) {
            $table->dropColumn([
                'direct_topup_min_quantity',
                'direct_topup_max_quantity',
                'direct_topup_price_per_unit',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('supplier_product_mappings', function (Blueprint $table) {
            $table->decimal('direct_topup_min_quantity', 20, 4)->nullable()->after('direct_topup_account_label');
            $table->decimal('direct_topup_max_quantity', 20, 4)->nullable()->after('direct_topup_min_quantity');
            $table->decimal('direct_topup_price_per_unit', 24, 8)->nullable()->after('direct_topup_max_quantity');
        });
    }
};
