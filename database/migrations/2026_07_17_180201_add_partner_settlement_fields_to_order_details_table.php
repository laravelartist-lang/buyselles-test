<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->unsignedBigInteger('partner_supplier_api_id')->nullable()->after('supplier_denomination_id');
            $table->unsignedBigInteger('partner_supplier_product_mapping_id')->nullable()->after('partner_supplier_api_id');
            $table->decimal('partner_supplier_cost_total', 24, 10)->default(0)->after('partner_supplier_product_mapping_id');
            $table->decimal('partner_admin_margin', 24, 10)->default(0)->after('partner_supplier_cost_total');

            $table->index('partner_supplier_api_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->dropIndex(['partner_supplier_api_id']);
            $table->dropColumn([
                'partner_supplier_api_id',
                'partner_supplier_product_mapping_id',
                'partner_supplier_cost_total',
                'partner_admin_margin',
            ]);
        });
    }
};
