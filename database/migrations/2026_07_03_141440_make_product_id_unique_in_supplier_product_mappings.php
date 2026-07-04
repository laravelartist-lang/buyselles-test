<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_product_mappings', function (Blueprint $table) {
            $table->dropForeign('supplier_product_mappings_product_id_foreign');
            $table->dropUnique('supplier_product_mappings_product_id_supplier_api_id_unique');
            $table->unique('product_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_product_mappings', function (Blueprint $table) {
            $table->dropForeign('supplier_product_mappings_product_id_foreign');
            $table->dropUnique('supplier_product_mappings_product_id_unique');
            $table->unique(['product_id', 'supplier_api_id']);
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }
};
