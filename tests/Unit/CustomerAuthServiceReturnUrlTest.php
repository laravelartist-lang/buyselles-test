<?php

namespace Tests\Unit;

use App\Services\Web\CustomerAuthService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CustomerAuthServiceReturnUrlTest extends TestCase
{
    public function test_it_rejects_admin_and_login_return_urls(): void
    {
        $service = new CustomerAuthService;

        $this->assertNull($service->sanitizeCustomerReturnUrl(url('/login/admin-secret')));
        $this->assertNull($service->sanitizeCustomerReturnUrl(url('/admin/dashboard')));
        $this->assertNull($service->sanitizeCustomerReturnUrl(url('/vendor/dashboard')));
        $this->assertNull($service->sanitizeCustomerReturnUrl(url('/customer/auth/login')));
    }

    public function test_it_keeps_storefront_return_urls(): void
    {
        $service = new CustomerAuthService;

        $checkoutUrl = url('/checkout-details');

        $this->assertSame($checkoutUrl, $service->sanitizeCustomerReturnUrl($checkoutUrl));
    }

    public function test_it_defaults_to_home_when_session_has_admin_login_url(): void
    {
        Config::set('app.url', 'http://buyselles.test');

        session()->put('keep_customer_login_redirect_url', url('/login/admin-secret'));

        $service = new CustomerAuthService;

        $this->assertSame(url('/'), $service->getCustomerAuthReturnURL());
    }
}
