<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_product_mappings', function (Blueprint $table) {
            $table->boolean('is_direct_topup')->default(false)->after('is_customizable');
            $table->string('direct_topup_account_label', 255)->nullable()->after('is_direct_topup');
            $table->decimal('direct_topup_min_quantity', 20, 4)->nullable()->after('direct_topup_account_label');
            $table->decimal('direct_topup_max_quantity', 20, 4)->nullable()->after('direct_topup_min_quantity');
            $table->decimal('direct_topup_price_per_unit', 24, 8)->nullable()->after('direct_topup_max_quantity');
        });

        DB::statement('
            UPDATE supplier_product_mappings m
            INNER JOIN products p ON p.id = m.product_id
            SET
                m.is_direct_topup = COALESCE(p.is_direct_topup, 0),
                m.direct_topup_account_label = p.direct_topup_account_label,
                m.direct_topup_min_quantity = p.direct_topup_min_quantity,
                m.direct_topup_max_quantity = p.direct_topup_max_quantity,
                m.direct_topup_price_per_unit = p.direct_topup_price_per_unit
            WHERE p.is_direct_topup = 1
        ');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'is_direct_topup',
                'direct_topup_account_label',
                'direct_topup_min_quantity',
                'direct_topup_max_quantity',
                'direct_topup_price_per_unit',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_direct_topup')->default(false)->after('digital_product_type');
            $table->string('direct_topup_account_label', 255)->nullable()->after('is_direct_topup');
            $table->decimal('direct_topup_min_quantity', 20, 4)->nullable()->after('direct_topup_account_label');
            $table->decimal('direct_topup_max_quantity', 20, 4)->nullable()->after('direct_topup_min_quantity');
            $table->decimal('direct_topup_price_per_unit', 24, 8)->nullable()->after('direct_topup_max_quantity');
        });

        DB::statement('
            UPDATE products p
            INNER JOIN supplier_product_mappings m ON m.product_id = p.id
            SET
                p.is_direct_topup = COALESCE(m.is_direct_topup, 0),
                p.direct_topup_account_label = m.direct_topup_account_label,
                p.direct_topup_min_quantity = m.direct_topup_min_quantity,
                p.direct_topup_max_quantity = m.direct_topup_max_quantity,
                p.direct_topup_price_per_unit = m.direct_topup_price_per_unit
            WHERE m.is_direct_topup = 1
        ');

        Schema::table('supplier_product_mappings', function (Blueprint $table) {
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
