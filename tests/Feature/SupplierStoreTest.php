<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\Supplier\SupplierController;
use App\Models\SupplierApi;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class SupplierStoreTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->recreateTable('business_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->nullable();
            $table->longText('value')->nullable();
            $table->timestamps();
        });
        $this->app['db']->table('business_settings')->insert([
            'type' => 'language',
            'value' => json_encode([
                ['code' => 'en', 'name' => 'English', 'default' => true, 'direction' => 'ltr'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->recreateTable('supplier_apis', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('driver', 50);
            $table->string('base_url', 500);
            $table->text('credentials');
            $table->string('auth_type', 50)->default('api_key');
            $table->json('settings')->nullable();
            $table->unsignedInteger('rate_limit_per_minute')->default(60);
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_sandbox')->default(false);
            $table->boolean('supports_direct_top_up')->default(false);
            $table->string('health_status', 20)->default('unknown');
            $table->timestamp('health_checked_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_add_supplier_returns_validation_errors_when_required_fields_missing(): void
    {
        $controller = app(SupplierController::class);

        $response = $controller->add(Request::create('/admin/supplier/add', 'POST', [
            'name' => '',
            'driver' => 'bamboo',
            'base_url' => 'https://api.bamboocardportal.com',
            'auth_type' => 'basic',
        ]));

        $this->assertTrue(session()->has('errors'));
        $this->assertSame(0, SupplierApi::query()->count());
        $this->assertTrue($response->isRedirect());
    }

    public function test_add_supplier_stores_bamboo_supplier_with_credentials(): void
    {
        $controller = app(SupplierController::class);

        $response = $controller->add(Request::create('/admin/supplier/add', 'POST', [
            'name' => 'Bamboo Test',
            'driver' => 'bamboo',
            'base_url' => 'https://api.bamboocardportal.com',
            'auth_type' => 'basic',
            'rate_limit_per_minute' => 60,
            'priority' => 0,
            'credentials' => [
                'client_id' => 'client-123',
                'client_secret' => 'secret-456',
            ],
            'settings' => [
                'face_value' => '10',
            ],
        ]));

        $this->assertTrue($response->isRedirect());
        $this->assertFalse(session()->has('errors'));

        $supplier = SupplierApi::query()->first();
        $this->assertNotNull($supplier);
        $this->assertSame('bamboo', $supplier->driver);
        $this->assertSame('basic', $supplier->auth_type);
        $this->assertSame('client-123', $supplier->getDecryptedCredentials()['client_id'] ?? null);
    }

    public function test_add_supplier_requires_bamboo_credentials(): void
    {
        $controller = app(SupplierController::class);

        $response = $controller->add(Request::create('/admin/supplier/add', 'POST', [
            'name' => 'Bamboo Missing Creds',
            'driver' => 'bamboo',
            'base_url' => 'https://api.bamboocardportal.com',
            'auth_type' => 'basic',
            'rate_limit_per_minute' => 60,
            'priority' => 0,
            'credentials' => [],
        ]));

        $this->assertTrue(session()->has('errors'));
        $this->assertSame(0, SupplierApi::query()->count());
        $this->assertTrue($response->isRedirect());
    }
}
