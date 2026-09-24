<?php

namespace Tests\Unit;

use App\Services\Apple\AppStoreConnectClient;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppStoreConnectClientPriceScheduleTest extends TestCase
{
    public function test_it_posts_price_schedule_instead_of_patching_in_app_purchase_prices_relationship(): void
    {
        Config::set('apple_app_store.key_id', 'TESTKEYID');
        Config::set('apple_app_store.issuer_id', '11111111-1111-1111-1111-111111111111');
        Config::set('apple_app_store.private_key', $this->samplePrivateKey());

        Http::fake([
            '*' => Http::response([
                'data' => [
                    'type' => 'inAppPurchasePriceSchedules',
                    'id' => 'schedule-1',
                ],
            ], 201),
        ]);

        $client = new AppStoreConnectClient;
        $client->setInAppPurchasePrice('6446452615', 'NjQ0NjQ1MjYxNV91c181', 'USA');

        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/v1/inAppPurchasePriceSchedules');
        });
    }

    private function samplePrivateKey(): string
    {
        return "-----BEGIN PRIVATE KEY-----\n"
            ."MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQgbIlUtcXJo+kHRVoZ\n"
            ."WU0/0HTScS+R4X8AbmFvtTv10XKhRANCAARGGr/t24wQFp4d3uaqUWMT/6iF3wff\n"
            ."jS8jG0pKeB17UW2rIwYR3JTNgzpmKKRZ5vUO04Yni6xO2nQ5flv/NMgy\n"
            .'-----END PRIVATE KEY-----';
    }
}
