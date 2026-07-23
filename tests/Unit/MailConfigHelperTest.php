<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
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
                'value' => json_encode(['status' => '1', 'host' => 'smtp.sendgrid.net', 'username' => 'apikey']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        clearWebConfigCacheKeys();

        $this->assertTrue(isMailConfigActive());
        $this->assertSame('smtp.sendgrid.net', getActiveMailConfig()['host']);
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
