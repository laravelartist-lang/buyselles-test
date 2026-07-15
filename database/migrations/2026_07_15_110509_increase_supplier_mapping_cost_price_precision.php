<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_product_mappings', function (Blueprint $table): void {
            $table->decimal('cost_price', 24, 10)->default(0)->change();
        });

        Schema::table('supplier_orders', function (Blueprint $table): void {
            $table->decimal('cost_per_unit', 24, 10)->change();
            $table->decimal('total_cost', 24, 10)->change();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_product_mappings', function (Blueprint $table): void {
            $table->decimal('cost_price', 10, 2)->default(0)->change();
        });

        Schema::table('supplier_orders', function (Blueprint $table): void {
            $table->decimal('cost_per_unit', 10, 2)->change();
            $table->decimal('total_cost', 10, 2)->change();
        });
    }
};
