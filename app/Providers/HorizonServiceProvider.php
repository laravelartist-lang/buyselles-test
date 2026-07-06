<?php

namespace App\Providers;

use App\Models\Admin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        Horizon::auth(function (): bool {
            return $this->adminCanAccessHorizon();
        });
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (): bool {
            return $this->adminCanAccessHorizon();
        });
    }

    private function adminCanAccessHorizon(): bool
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin instanceof Admin) {
            return false;
        }

        if ($admin->id !== 1 && (int) $admin->status !== 1) {
            return false;
        }

        return true;
    }
}
