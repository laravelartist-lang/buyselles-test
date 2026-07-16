<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_product_mappings', function (Blueprint $table): void {
            if (! Schema::hasColumn('supplier_product_mappings', 'direct_topup_bundle_quantity')) {
                $table->decimal('direct_topup_bundle_quantity', 20, 4)
                    ->nullable()
                    ->after('direct_topup_region');
            }
        });
    }

    public function down(): void
    {
        Schema::table('supplier_product_mappings', function (Blueprint $table): void {
            if (Schema::hasColumn('supplier_product_mappings', 'direct_topup_bundle_quantity')) {
                $table->dropColumn('direct_topup_bundle_quantity');
            }
        });
    }
};
