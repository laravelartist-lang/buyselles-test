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
                'auto_restock',
                'min_stock_threshold',
                'max_restock_qty',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('supplier_product_mappings', function (Blueprint $table) {
            $table->boolean('auto_restock')->default(true)->after('priority');
            $table->unsignedInteger('min_stock_threshold')->default(5)->after('auto_restock');
            $table->unsignedInteger('max_restock_qty')->default(50)->after('min_stock_threshold');
        });
    }
};
