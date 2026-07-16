<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'partner_api_only')) {
                $column = $table->boolean('partner_api_only')->default(false);

                if (Schema::hasColumn('products', 'partner_approved')) {
                    $column->after('partner_approved');
                }

                $table->index('partner_api_only', 'products_partner_api_only_index');
            }
        });

        // Hide products previously auto-created for Partner API catalog assignment.
        DB::table('products')
            ->where('details', 'like', 'Partner API catalog item%')
            ->update(['partner_api_only' => true]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'partner_api_only')) {
                $table->dropIndex('products_partner_api_only_index');
                $table->dropColumn('partner_api_only');
            }
        });
    }
};
