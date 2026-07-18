<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_product_mappings', function (Blueprint $table): void {
            $table->string('code_source_priority', 32)
                ->default('local_first')
                ->after('priority')
                ->comment('local_first = use local pool before supplier; supplier_first = supplier before local pool');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_product_mappings', function (Blueprint $table): void {
            $table->dropColumn('code_source_priority');
        });
    }
};
