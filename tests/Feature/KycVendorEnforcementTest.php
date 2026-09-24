<?php

namespace Tests\Feature;

use App\Enums\KycStatus;
use App\Enums\KycUserType;
use App\Http\Middleware\SellerApiKycMiddleware;
use App\Http\Middleware\SellerMiddleware;
use App\Models\KycVerification;
use App\Models\Seller;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

/**
 * Vendor KYC enforcement on the vendor panel and the vendor app.
 *
 * A freshly registered vendor may sign in - and must be able to reach the KYC
 * screens - but every other vendor endpoint is closed until the verification
 * is approved. That has to hold on both surfaces.
 */
class KycVendorEnforcementTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'sumsub.enabled' => true,
            'sumsub.app_token' => 'test-app-token',
            'sumsub.secret_key' => 'test-secret-key',
            'sumsub.webhook_secret' => 'test-webhook-secret',
            'sumsub.levels.customer' => 'customer-kyc',
            'sumsub.levels.vendor' => 'vendor-kyc',
        ]);

        $this->recreateTable('business_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->nullable();
            $table->longText('value')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('sellers', function (Blueprint $table): void {
            $table->id();
            $table->string('f_name')->nullable();
            $table->string('l_name')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->default('pending');
            $table->string('auth_token')->nullable();
            $table->timestamps();
        });

        $this->recreateTable('kyc_verifications', function (Blueprint $table): void {
            $table->id();
            $table->string('user_type', 20);
            $table->unsignedBigInteger('user_id');
            $table->string('external_user_id', 120)->unique();
            $table->string('applicant_id', 120)->nullable();
            $table->string('level_name', 120);
            $table->string('status', 30)->default('not_started');
            $table->string('review_answer', 20)->nullable();
            $table->string('reject_type', 20)->nullable();
            $table->json('reject_labels')->nullable();
            $table->text('moderation_comment')->nullable();
            $table->timestamp('required_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('last_webhook_payload')->nullable();
            $table->timestamps();
        });

        $this->storeSetting('language', json_encode([
            ['code' => 'en', 'name' => 'English', 'default' => true, 'direction' => 'ltr'],
        ]));
        $this->storeSetting('kyc_verification_status', '1');

        Http::fake([
            'api.sumsub.com/*' => Http::response([
                'id' => 'app-vendor-1',
                'reviewStatus' => 'init',
                'idDocs' => [],
            ], 200),
        ]);

        Cache::flush();
    }

    public function test_a_new_vendor_is_sent_to_kyc_rather_than_locked_out_of_the_panel(): void
    {
        $seller = $this->vendor();

        $response = $this->actingAs($seller, 'seller')->get(route('vendor.dashboard.index'));

        // Sent to the KYC screen, not logged out - a brand new vendor with a
        // pending account must still be able to complete verification.
        $response->assertRedirect(route('vendor.kyc.index'));
        $this->assertNotSame(route('vendor.auth.login'), $response->headers->get('Location'));
    }

    public function test_the_vendor_app_allows_dashboard_preview_before_kyc_is_approved(): void
    {
        $seller = $this->vendor();

        $reached = false;

        $response = app(SellerApiKycMiddleware::class)->handle(
            $this->vendorApiRequest('api/v3/seller/seller-info', 'GET', $seller),
            function () use (&$reached): Response {
                $reached = true;

                return new Response('preview');
            }
        );

        $this->assertTrue($reached, 'Dashboard preview GET was blocked before KYC approval.');
        $this->assertSame('preview', $response->getContent());
    }

    public function test_the_vendor_app_still_blocks_mutating_routes_until_kyc_is_approved(): void
    {
        $seller = $this->vendor();

        $reached = false;

        $response = app(SellerApiKycMiddleware::class)->handle(
            $this->vendorApiRequest('api/v3/seller/balance-withdraw', 'POST', $seller, ['amount' => 100]),
            function () use (&$reached): Response {
                $reached = true;

                return new Response('withdrawn');
            }
        );

        $this->assertFalse($reached, 'Mutating vendor routes must stay blocked before KYC approval.');
        $this->assertSame(403, $response->getStatusCode());
        $this->assertTrue(json_decode($response->getContent(), true)['kyc_required'] ?? false);
    }

    public function test_the_vendor_app_allows_read_only_order_list_before_kyc_is_approved(): void
    {
        $seller = $this->vendor();

        $reached = false;

        $response = app(SellerApiKycMiddleware::class)->handle(
            $this->vendorApiRequest('api/v3/seller/orders/list', 'POST', $seller, [
                'limit' => 10,
                'offset' => 1,
                'status' => 'all',
            ]),
            function () use (&$reached): Response {
                $reached = true;

                return new Response('listed');
            }
        );

        $this->assertTrue($reached, 'Read-only order list POST was blocked before KYC approval.');
        $this->assertSame('listed', $response->getContent());
    }

    public function test_the_vendor_app_keeps_the_kyc_endpoints_reachable_while_closed(): void
    {
        $seller = $this->vendor();

        $response = $this->withHeader('Authorization', 'Bearer '.$seller->auth_token)
            ->getJson('api/v3/seller/kyc/status');

        $response->assertOk();
        $response->assertJsonPath('kyc.status', KycStatus::NOT_STARTED);
    }

    public function test_an_approved_vendor_is_no_longer_blocked_on_the_vendor_app(): void
    {
        $seller = $this->vendor();
        $this->approveVendor($seller->id);

        $response = $this->withHeader('Authorization', 'Bearer '.$seller->auth_token)
            ->getJson('api/v3/seller/kyc/status');

        $response->assertOk();
        $response->assertJsonPath('kyc.status', KycStatus::APPROVED);
        $response->assertJsonPath('kyc.is_verified', true);
    }

    public function test_the_vendor_app_rejects_requests_without_a_token(): void
    {
        $this->getJson('api/v3/seller/seller-info')->assertStatus(401);
    }

    public function test_the_vendor_surfaces_are_wired_to_the_kyc_enforcement(): void
    {
        $this->assertMiddlewarePresent('vendor/dashboard', SellerMiddleware::class, 'seller');
        $this->assertMiddlewarePresent('api/v3/seller/seller-info', SellerApiKycMiddleware::class, 'seller_api_kyc');
    }

    public function test_the_panel_gate_stands_down_when_kyc_is_disabled(): void
    {
        $this->storeSetting('kyc_verification_status', '0');

        $seller = $this->vendor(status: 'approved');
        $this->actingAs($seller, 'seller');

        $reached = false;

        $response = app(SellerMiddleware::class)->handle(
            Request::create('vendor/dashboard', 'GET'),
            function () use (&$reached): Response {
                $reached = true;

                return new Response('vendored');
            }
        );

        $this->assertTrue($reached, 'The vendor panel gate blocked a request while KYC is disabled.');
        $this->assertSame('vendored', $response->getContent());
    }

    public function test_a_verified_vendor_whose_account_is_not_approved_is_logged_out(): void
    {
        $seller = $this->vendor(status: 'pending');
        $this->approveVendor($seller->id);

        $this->actingAs($seller, 'seller');

        $response = app(SellerMiddleware::class)->handle(
            Request::create('vendor/dashboard', 'GET'),
            fn (): Response => new Response('vendored')
        );

        $this->assertTrue($response->isRedirect());
        $this->assertSame(route('vendor.auth.login'), $response->headers->get('Location'));
    }

    private function assertMiddlewarePresent(string $uri, string $class, string $alias): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($route): bool => $route->uri() === $uri);

        $this->assertNotNull($route, "Route [{$uri}] is not registered.");

        $middleware = $route->gatherMiddleware();

        $this->assertTrue(
            in_array($alias, $middleware, true) || in_array($class, $middleware, true),
            "Route [{$uri}] is not behind the KYC middleware. Found: ".implode(', ', $middleware)
        );
    }

    private function vendor(string $status = 'pending'): Seller
    {
        return Seller::create([
            'f_name' => 'Test',
            'l_name' => 'Vendor',
            'email' => 'vendor-'.uniqid().'@example.com',
            'status' => $status,
            'auth_token' => bin2hex(random_bytes(24)),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function vendorApiRequest(string $uri, string $method, Seller $seller, array $payload = []): Request
    {
        $request = Request::create($uri, $method, $payload);
        $request->headers->set('Authorization', 'Bearer '.$seller->auth_token);
        $request->merge(['seller' => $seller]);

        return $request;
    }

    private function approveVendor(int $sellerId): void
    {
        KycVerification::create([
            'user_type' => KycUserType::VENDOR,
            'user_id' => $sellerId,
            'external_user_id' => 'vendor_'.$sellerId,
            'level_name' => 'vendor-kyc',
            'status' => KycStatus::APPROVED,
            'required_at' => now()->subDay(),
            'verified_at' => now(),
        ]);
    }

    private function storeSetting(string $type, string $value): void
    {
        DB::table('business_settings')->updateOrInsert(
            ['type' => $type],
            ['value' => $value, 'updated_at' => now(), 'created_at' => now()],
        );

        Cache::flush();
    }
}
