<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_product_mappings', function (Blueprint $table): void {
            if (! Schema::hasColumn('supplier_product_mappings', 'direct_topup_bundle_mode')) {
                $table->string('direct_topup_bundle_mode', 20)
                    ->default('customizable')
                    ->after('direct_topup_account_label');
            }
        });
    }

    public function down(): void
    {
        Schema::table('supplier_product_mappings', function (Blueprint $table): void {
            if (Schema::hasColumn('supplier_product_mappings', 'direct_topup_bundle_mode')) {
                $table->dropColumn('direct_topup_bundle_mode');
            }
        });
    }
};
