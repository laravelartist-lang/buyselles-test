<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\Product;
use App\Models\SupplierProductMapping;
use App\Observers\OrderObserver;
use App\Observers\SupplierProductMappingObserver;
use Illuminate\Support\ServiceProvider;

class ObserverServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Product::observe([]);
        Order::observe(OrderObserver::class);
        SupplierProductMapping::observe(SupplierProductMappingObserver::class);
    }
}
