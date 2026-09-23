<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || Schema::hasColumn('orders', 'review_reason')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->string('review_reason')->nullable()->after('order_status');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'review_reason')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('review_reason');
        });
    }
};
