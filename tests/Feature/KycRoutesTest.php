<?php

namespace Tests\Feature;

use App\Services\Kyc\CustomerCheckoutKycGuard;
use App\Services\Kyc\KycService;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class KycRoutesTest extends TestCase
{
    /**
     * The same access token endpoint backs the web storefront, the customer
     * app and the vendor app, so every client must expose the KYC flow.
     */
    public function test_the_customer_kyc_endpoints_are_registered(): void
    {
        $this->assertTrue(Route::has('customer.kyc.index'));
        $this->assertTrue(Route::has('customer.kyc.token'));
        $this->assertTrue(Route::has('customer.kyc.status'));

        $this->assertSame('customer/kyc', $this->uri('customer.kyc.index'));
        $this->assertSame('customer/kyc/token', $this->uri('customer.kyc.token'));
    }

    public function test_the_vendor_kyc_endpoints_are_registered(): void
    {
        $this->assertTrue(Route::has('vendor.kyc.index'));
        $this->assertTrue(Route::has('vendor.kyc.token'));
        $this->assertTrue(Route::has('vendor.kyc.status'));

        $this->assertSame('vendor/kyc', $this->uri('vendor.kyc.index'));
    }

    public function test_the_admin_kyc_endpoints_are_registered(): void
    {
        $this->assertTrue(Route::has('admin.kyc.index'));
        $this->assertTrue(Route::has('admin.kyc.settings'));
        $this->assertTrue(Route::has('admin.kyc.settings.update'));
        $this->assertTrue(Route::has('admin.kyc.sync'));
        $this->assertTrue(Route::has('admin.kyc.reset'));
    }

    public function test_the_customer_app_kyc_endpoints_are_registered(): void
    {
        $this->assertSame('api/v1/customer/kyc/status', $this->uriForMethod('GET', 'api/v1/customer/kyc/status'));
        $this->assertSame('api/v1/customer/kyc/token', $this->uriForMethod('POST', 'api/v1/customer/kyc/token'));

        $this->assertContains('auth:api', $this->findRoute('GET', 'api/v1/customer/kyc/status')->gatherMiddleware());
    }

    public function test_the_vendor_app_kyc_endpoints_are_registered(): void
    {
        $this->assertSame('api/v3/seller/kyc/status', $this->uriForMethod('GET', 'api/v3/seller/kyc/status'));
        $this->assertSame('api/v3/seller/kyc/token', $this->uriForMethod('POST', 'api/v3/seller/kyc/token'));
    }

    public function test_the_vendor_app_kyc_gate_covers_every_other_vendor_endpoint(): void
    {
        $route = $this->findRoute('GET', 'api/v3/seller/seller-info');

        $this->assertNotNull($route);
        $this->assertContains('seller_api_kyc', $route->gatherMiddleware());
    }

    public function test_the_vendor_api_kyc_endpoints_are_not_blocked_by_the_gate(): void
    {
        $route = $this->findRoute('GET', 'api/v3/seller/kyc/status');

        $this->assertNotNull($route);
        $this->assertContains('seller_api_kyc', $route->gatherMiddleware());
    }

    public function test_the_signed_launch_urls_used_by_the_mobile_apps_are_registered(): void
    {
        $this->assertTrue(Route::has('kyc.launch'));
        $this->assertTrue(Route::has('kyc.launch.token'));

        $launch = Route::getRoutes()->getByName('kyc.launch');

        $this->assertSame('kyc/launch/{userType}/{userId}', $launch->uri());
        $this->assertContains('signed', $launch->gatherMiddleware());
    }

    public function test_the_mobile_apps_can_request_a_launch_url(): void
    {
        $this->assertSame('api/v1/customer/kyc/launch-url', $this->uriForMethod('GET', 'api/v1/customer/kyc/launch-url'));
        $this->assertSame('api/v3/seller/kyc/launch-url', $this->uriForMethod('GET', 'api/v3/seller/kyc/launch-url'));
    }

    public function test_the_sumsub_webhook_is_registered_without_session_middleware(): void
    {
        $route = $this->findRoute('POST', 'webhooks/sumsub');

        $this->assertNotNull($route);
        $this->assertSame('kyc.sumsub.webhook', $route->getName());
        $this->assertSame([], $route->gatherMiddleware());
    }

    public function test_the_kyc_services_are_resolvable(): void
    {
        $this->assertInstanceOf(KycService::class, app(KycService::class));
        $this->assertInstanceOf(CustomerCheckoutKycGuard::class, app(CustomerCheckoutKycGuard::class));
    }

    private function uri(string $name): string
    {
        return Route::getRoutes()->getByName($name)->uri();
    }

    private function uriForMethod(string $method, string $uri): string
    {
        $route = $this->findRoute($method, $uri);

        $this->assertNotNull($route, "Route [{$method} {$uri}] was not registered.");

        return $route->uri();
    }

    private function findRoute(string $method, string $uri): ?RoutingRoute
    {
        foreach (Route::getRoutes() as $route) {
            if ($route->uri() === $uri && in_array($method, $route->methods(), true)) {
                return $route;
            }
        }

        return null;
    }
}
