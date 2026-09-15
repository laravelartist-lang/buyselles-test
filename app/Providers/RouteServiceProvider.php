<?php

namespace App\Providers;

use App\Http\Controllers\Kyc\KycLaunchController;
use App\Http\Controllers\Kyc\SumsubWebhookController;
use App\Http\Requests\Request;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'App\Http\Controllers';

    /**
     * The path to the "home" route for your application.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        //

        parent::boot();
        $this->configureRateLimiting();
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        $this->mapApiRoutes();
        $this->mapApiv2Routes();
        $this->mapApiv3Routes();

        // $this->mapInstallRoutes();
        // $this->mapUpdateRoutes();

        $this->mapBetaAdminRoutes();
        $this->mapBetaVendorRoutes();
        $this->mapBetaWebRoutes();
        $this->mapKycWebhookRoutes();
        $this->mapKycLaunchRoutes();
        $this->mapDemoRoutes();
    }

    /**
     * Define the Sumsub WebSDK launcher used by the mobile apps.
     *
     * The URLs are signed, so the WebView can render the SDK for the right
     * applicant without exposing Sumsub credentials or a session cookie.
     */
    protected function mapKycLaunchRoutes(): void
    {
        Route::middleware('signed')->group(function () {
            Route::get('kyc/launch/{userType}/{userId}', KycLaunchController::class)
                ->name('kyc.launch');
            Route::get('kyc/launch/{userType}/{userId}/token', [KycLaunchController::class, 'token'])
                ->name('kyc.launch.token');
        });
    }

    /**
     * Define the Sumsub webhook endpoint.
     *
     * Registered without middleware on purpose: Sumsub delivers signed
     * payloads server to server, so sessions, CSRF protection and throttling
     * must not apply.
     */
    protected function mapKycWebhookRoutes(): void
    {
        Route::post(
            config('sumsub.webhook_path', 'webhooks/sumsub'),
            SumsubWebhookController::class
        )->name('kyc.sumsub.webhook');
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapInstallRoutes()
    {
        Route::middleware('web')
            ->namespace($this->namespace)
            ->group(base_path('routes/install.php'));
    }

    protected function mapUpdateRoutes()
    {
        Route::middleware('web')
            ->namespace($this->namespace)
            ->group(base_path('routes/update.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        Route::prefix('api')
            ->middleware('api')
            ->namespace($this->namespace)
            ->group(base_path('routes/rest_api/v1/api.php'));
    }

    protected function mapApiv2Routes()
    {
        Route::prefix('api')
            ->middleware('api')
            ->namespace($this->namespace)
            ->group(base_path('routes/rest_api/v2/api.php'));
    }

    protected function mapApiv3Routes()
    {
        Route::prefix('api')
            ->middleware('api')
            ->namespace($this->namespace)
            ->group(base_path('routes/rest_api/v3/seller.php'));
    }

    /**
     * Define the "beta" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     */
    protected function mapBetaAdminRoutes(): void
    {
        Route::middleware('web')
            ->namespace($this->namespace)
            ->group(base_path('routes/admin/routes.php'));
    }

    protected function mapBetaVendorRoutes(): void
    {
        Route::middleware('web')
            ->namespace($this->namespace)
            ->group(base_path('routes/vendor/routes.php'));
    }

    protected function mapBetaWebRoutes(): void
    {
        Route::middleware(['web', 'logUserBrowsingNavigation'])
            ->namespace($this->namespace)
            ->group(base_path('routes/web/routes.php'));
    }

    protected function mapDemoRoutes(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        Route::middleware('web')
            ->namespace($this->namespace)
            ->group(base_path('routes/demo.php'));
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('global', function (Request $request) {
            return Limit::perMinute(3000);
        });
    }
}
