<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_catalogs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('partner_catalog_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('partner_catalog_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('partner_price', 24, 10)->nullable();
            $table->string('variable_price_type', 20)->nullable();
            $table->decimal('variable_price_value', 24, 10)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('partner_catalog_id', 'pci_catalog_fk')
                ->references('id')
                ->on('partner_catalogs')
                ->cascadeOnDelete();
            $table->foreign('product_id', 'pci_product_fk')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();
            $table->unique(['partner_catalog_id', 'product_id'], 'pci_catalog_product_unique');
            $table->index(['partner_catalog_id', 'is_active'], 'pci_catalog_active_index');
        });

        Schema::create('partner_catalog_denomination_prices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('partner_catalog_item_id');
            $table->unsignedBigInteger('supplier_product_denomination_id');
            $table->decimal('partner_price', 24, 10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('partner_catalog_item_id', 'pcdp_item_fk')
                ->references('id')
                ->on('partner_catalog_items')
                ->cascadeOnDelete();
            $table->foreign('supplier_product_denomination_id', 'pcdp_denom_fk')
                ->references('id')
                ->on('supplier_product_denominations')
                ->cascadeOnDelete();
            $table->unique(
                ['partner_catalog_item_id', 'supplier_product_denomination_id'],
                'pcdp_item_denom_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_catalog_denomination_prices');
        Schema::dropIfExists('partner_catalog_items');
        Schema::dropIfExists('partner_catalogs');
    }
};
