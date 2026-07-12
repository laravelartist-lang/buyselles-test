<?php

namespace Tests\Unit;

use App\Services\Supplier\SupplierManager;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class SupplierManagerAdminUiTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Cache::flush();
        $this->recreateTable('business_settings', function (\Illuminate\Database\Schema\Blueprint $table): void {
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
    }

    public function test_admin_ui_driver_keys_only_include_generic_rest_and_bamboo(): void
    {
        $manager = app(SupplierManager::class);

        $this->assertSame(['generic_rest', 'bamboo'], $manager->getAdminUiDriverKeys());
    }

    public function test_admin_ui_driver_schemas_only_include_supported_drivers(): void
    {
        $manager = app(SupplierManager::class);

        $schemas = $manager->getAdminUiDriversWithSchemas();

        $this->assertArrayHasKey('generic_rest', $schemas);
        $this->assertArrayHasKey('bamboo', $schemas);
        $this->assertArrayNotHasKey('reloadly', $schemas);
        $this->assertArrayNotHasKey('kinguin', $schemas);
        $this->assertArrayNotHasKey('golf_api', $schemas);
        $this->assertArrayHasKey('client_id', $schemas['bamboo']['credentials']);
        $this->assertArrayHasKey('products_endpoint', $schemas['generic_rest']['settings']);
    }

    public function test_admin_ui_driver_presets_define_auth_and_defaults(): void
    {
        $manager = app(SupplierManager::class);

        $presets = $manager->getAdminUiDriverPresets();

        $this->assertSame('https://api.bamboocardportal.com', $presets['bamboo']['base_url']);
        $this->assertSame(['basic'], $presets['bamboo']['auth_types']);
        $this->assertFalse($presets['bamboo']['supports_direct_top_up']);
        $this->assertTrue($presets['generic_rest']['supports_direct_top_up']);
        $this->assertContains('login_via', $presets['generic_rest']['auth_types']);
        $this->assertSame(['email', 'password'], $presets['generic_rest']['credential_fields_by_auth']['login_via']);
    }

    public function test_validate_credentials_for_bamboo_requires_client_id_and_secret(): void
    {
        $manager = app(SupplierManager::class);

        $errors = $manager->validateCredentialsForDriver('bamboo', 'basic', [], true);

        $this->assertArrayHasKey('credentials.client_id', $errors);
        $this->assertArrayHasKey('credentials.client_secret', $errors);
    }
}
