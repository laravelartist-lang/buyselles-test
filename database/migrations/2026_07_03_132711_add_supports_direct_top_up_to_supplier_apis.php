<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_apis', function (Blueprint $table) {
            $table->boolean('supports_direct_top_up')->default(false)->after('is_sandbox');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_apis', function (Blueprint $table) {
            $table->dropColumn('supports_direct_top_up');
        });
    }
};
