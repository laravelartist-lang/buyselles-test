<?php

namespace Tests\Unit;

use App\Models\EmailTemplate;
use App\Traits\EmailTemplateTrait;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class EmailTemplateAutoProvisionTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recreateTable('email_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('template_name');
            $table->string('user_type');
            $table->string('template_design_name');
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('banner_image')->nullable();
            $table->string('image')->nullable();
            $table->string('logo')->nullable();
            $table->string('button_name')->nullable();
            $table->string('button_url')->nullable();
            $table->string('footer_text')->nullable();
            $table->string('copyright_text')->nullable();
            $table->json('pages')->nullable();
            $table->json('social_media')->nullable();
            $table->json('hide_field')->nullable();
            $table->tinyInteger('button_content_status')->default(1);
            $table->tinyInteger('product_information_status')->default(1);
            $table->tinyInteger('order_information_status')->default(1);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });

        $this->recreateTable('translations', function (Blueprint $table): void {
            $table->id();
            $table->string('translationable_type');
            $table->unsignedBigInteger('translationable_id');
            $table->string('locale')->nullable();
            $table->string('key')->nullable();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('storages', function (Blueprint $table): void {
            $table->id();
            $table->string('data_type');
            $table->unsignedBigInteger('data_id');
            $table->string('key');
            $table->string('value');
            $table->timestamps();
        });

        $this->recreateTable('business_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('social_medias', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('link')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });

        \DB::table('business_settings')->insert([
            [
                'type' => 'company_name',
                'value' => 'Buyselles',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'language',
                'value' => json_encode([['code' => 'en', 'default' => true, 'direction' => 'ltr']]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function test_sending_mail_provisions_missing_customer_template(): void
    {
        $helper = new class
        {
            use EmailTemplateTrait;

            public function provision(string $userType, string $templateName): bool
            {
                return $this->sendingMail(
                    sendMailTo: 'test@example.com',
                    userType: $userType,
                    templateName: $templateName,
                    data: [
                        'userName' => 'Test User',
                        'subject' => 'Test',
                        'verificationCode' => '123456',
                    ],
                    sendSync: false,
                );
            }
        };

        Mail::fake();

        $sent = $helper->provision('customer', 'registration-verification');

        $this->assertTrue($sent);
        $this->assertNotNull(
            EmailTemplate::where('user_type', 'customer')
                ->where('template_name', 'registration-verification')
                ->first()
        );
    }
}
