<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplier_apis') || ! Schema::hasColumn('supplier_apis', 'auth_type')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `supplier_apis` MODIFY `auth_type` VARCHAR(50) NOT NULL DEFAULT 'api_key'");
        } elseif ($driver === 'sqlite') {
            // SQLite tests recreate tables manually; no enum alteration needed.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('supplier_apis') || ! Schema::hasColumn('supplier_apis', 'auth_type')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `supplier_apis` MODIFY `auth_type` ENUM('api_key','bearer_token','oauth2','basic','hmac') NOT NULL DEFAULT 'api_key'");
        }
    }
};
