<?php

namespace Tests\Unit;

use App\Providers\MailConfigServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class MailConfigHelperTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recreateTable('business_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function test_get_active_mail_config_uses_sendgrid_when_smtp_is_disabled(): void
    {
        DB::table('business_settings')->insert([
            [
                'type' => 'mail_config',
                'value' => json_encode(['status' => 0, 'host' => 'mail.demo.com']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'mail_config_sendgrid',
                'value' => json_encode([
                    'status' => '1',
                    'host' => 'smtp.sendgrid.net',
                    'port' => '587',
                    'username' => 'apikey',
                    'password' => 'secret-key',
                    'encryption' => 'TLS',
                    'email_id' => 'support@example.com',
                    'name' => 'Example',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        clearWebConfigCacheKeys();

        $this->assertTrue(isMailConfigActive());
        $this->assertSame('smtp.sendgrid.net', getActiveMailConfig()['host']);
    }

    public function test_mail_config_service_provider_applies_legacy_and_mailer_settings(): void
    {
        Config::set('mail.driver', 'smtp');
        Config::set('mail.username', 'legacy-user');
        Config::set('mail.host', 'legacy-host.test');

        DB::table('business_settings')->insert([
            [
                'type' => 'mail_config',
                'value' => json_encode(['status' => 0]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'mail_config_sendgrid',
                'value' => json_encode([
                    'status' => '1',
                    'host' => 'smtp.sendgrid.net',
                    'port' => '587',
                    'username' => 'apikey',
                    'password' => 'secret-key',
                    'encryption' => 'TLS',
                    'email_id' => 'support@example.com',
                    'name' => 'Example',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        clearWebConfigCacheKeys();

        (new MailConfigServiceProvider($this->app))->boot();

        $this->assertSame('apikey', config('mail.username'));
        $this->assertSame('smtp.sendgrid.net', config('mail.host'));
        $this->assertSame('apikey', config('mail.mailers.smtp.username'));
        $this->assertSame('support@example.com', config('mail.from.address'));
    }

    public function test_get_active_mail_config_returns_null_when_both_providers_are_disabled(): void
    {
        DB::table('business_settings')->insert([
            [
                'type' => 'mail_config',
                'value' => json_encode(['status' => 0]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'mail_config_sendgrid',
                'value' => json_encode(['status' => '0']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        clearWebConfigCacheKeys();

        $this->assertFalse(isMailConfigActive());
        $this->assertNull(getActiveMailConfig());
    }
}
