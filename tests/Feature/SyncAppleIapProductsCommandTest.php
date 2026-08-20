<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class SyncAppleIapProductsCommandTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recreateTable('business_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->nullable();
            $table->longText('value')->nullable();
            $table->timestamps();
        });

        $this->app['db']->table('business_settings')->insert([
            ['type' => 'currency_model', 'value' => 'single_currency', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'system_default_currency', 'value' => '1', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->recreateTable('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->text('details')->nullable();
            $table->decimal('unit_price', 24, 2)->default(0);
            $table->string('product_type')->default('digital');
            $table->string('digital_product_type')->nullable();
            $table->string('apple_product_id')->nullable();
            $table->boolean('partner_api_only')->default(false);
            $table->integer('status')->default(1);
            $table->integer('request_status')->default(1);
            $table->timestamps();
        });
    }

    public function test_command_rejects_percent_and_fixed_usd_together(): void
    {
        $this->artisan('apple:iap-sync --percent=15 --fixed-usd=1')
            ->assertFailed();
    }

    public function test_dry_run_processes_digital_products_without_apple_credentials(): void
    {
        $this->app['db']->table('products')->insert([
            'id' => 101,
            'name' => 'PlayStation Gift Card',
            'details' => 'Digital code delivered after purchase',
            'unit_price' => 5,
            'product_type' => 'digital',
            'digital_product_type' => 'ready_after_sell',
            'status' => 1,
            'request_status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('apple:iap-sync --dry-run --percent=15 --product-id=101')
            ->expectsOutputToContain('Syncing digital products to App Store Connect')
            ->expectsOutputToContain('Markup mode: +15% on Buyselles price')
            ->expectsOutputToContain('[CREATED]')
            ->assertSuccessful();
    }

    public function test_dry_run_works_when_currency_tables_are_missing(): void
    {
        $this->app['db']->table('products')->insert([
            'id' => 101,
            'name' => 'PlayStation Gift Card',
            'details' => 'Digital code delivered after purchase',
            'unit_price' => 5,
            'product_type' => 'digital',
            'digital_product_type' => 'ready_after_sell',
            'status' => 1,
            'request_status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::dropIfExists('business_settings');

        $this->artisan('apple:iap-sync --dry-run --percent=15 --product-id=101')
            ->expectsOutputToContain('Currency tables are unavailable')
            ->expectsOutputToContain('[CREATED]')
            ->assertSuccessful();
    }
}
