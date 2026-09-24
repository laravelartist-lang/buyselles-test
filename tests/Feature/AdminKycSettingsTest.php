<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\KycManagementController;
use App\Services\Kyc\KycService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

/**
 * The admin toggle is stored in business_settings, but KYC only counts as
 * enabled once Sumsub credentials exist. This is the state the settings
 * page has to surface so the toggle never looks silently broken.
 */
class AdminKycSettingsTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'sumsub.enabled' => false,
            'sumsub.app_token' => null,
            'sumsub.secret_key' => null,
        ]);

        $this->recreateTable('business_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->nullable();
            $table->longText('value')->nullable();
            $table->timestamps();
        });

        $this->storeSetting('language', json_encode([
            ['code' => 'en', 'name' => 'English', 'default' => true, 'direction' => 'ltr'],
        ]));
    }

    public function test_the_settings_page_reports_sumsub_as_unconfigured_while_credentials_are_missing(): void
    {
        $data = app(KycManagementController::class)->settings()->getData();

        $this->assertFalse($data['sumsubConfigured']);
        $this->assertFalse($data['kycEnabled']);
    }

    public function test_a_stored_toggle_stays_off_until_sumsub_credentials_are_configured(): void
    {
        $this->storeSetting('kyc_verification_status', '1');

        $data = app(KycManagementController::class)->settings()->getData();

        $this->assertFalse($data['sumsubConfigured']);
        $this->assertFalse($data['kycEnabled']);
        $this->assertFalse(app(KycService::class)->isEnabled());
    }

    public function test_a_stored_toggle_reports_as_enabled_once_sumsub_credentials_are_configured(): void
    {
        $this->storeSetting('kyc_verification_status', '1');
        $this->configureSumsub();

        $data = app(KycManagementController::class)->settings()->getData();

        $this->assertTrue($data['sumsubConfigured']);
        $this->assertTrue($data['kycEnabled']);
        $this->assertTrue(app(KycService::class)->isEnabled());
    }

    public function test_updating_the_settings_persists_the_toggle_and_the_threshold(): void
    {
        $this->configureSumsub();

        $response = app(KycManagementController::class)->updateSettings(
            Request::create('/admin/kyc/settings', 'POST', [
                'kyc_verification_status' => '1',
                'kyc_customer_purchase_threshold' => '250',
            ])
        );

        $this->assertTrue($response->isRedirect());
        $this->assertSame('1', getWebConfig(name: 'kyc_verification_status'));
        $this->assertSame('250', getWebConfig(name: 'kyc_customer_purchase_threshold'));
        $this->assertTrue(app(KycService::class)->isEnabled());
    }

    public function test_unchecking_the_toggle_disables_kyc(): void
    {
        $this->configureSumsub();
        $this->storeSetting('kyc_verification_status', '1');

        app(KycManagementController::class)->updateSettings(
            Request::create('/admin/kyc/settings', 'POST', [
                'kyc_customer_purchase_threshold' => '100',
            ])
        );

        $this->assertSame('0', getWebConfig(name: 'kyc_verification_status'));
        $this->assertFalse(app(KycService::class)->isEnabled());
    }

    public function test_the_threshold_is_validated(): void
    {
        $this->expectException(ValidationException::class);

        try {
            app(KycManagementController::class)->updateSettings(
                Request::create('/admin/kyc/settings', 'POST', [
                    'kyc_customer_purchase_threshold' => '',
                ])
            );
        } finally {
            $this->assertNull(getWebConfig(name: 'kyc_customer_purchase_threshold'));
        }
    }

    private function configureSumsub(): void
    {
        config([
            'sumsub.enabled' => true,
            'sumsub.app_token' => 'test-app-token',
            'sumsub.secret_key' => 'test-secret-key',
        ]);
    }

    private function storeSetting(string $type, string $value): void
    {
        DB::table('business_settings')->insert([
            'type' => $type,
            'value' => $value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
