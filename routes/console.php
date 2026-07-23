<?php

use App\Console\Commands\MarkExpiredDigitalCodesCommand;
use App\Console\Commands\SyncExchangeRatesCommand;
use App\Jobs\AutoReleaseEscrowJob;
use App\Jobs\SupplierHealthCheckJob;
use App\Jobs\SyncSupplierMappingPricesJob;
use Illuminate\Support\Facades\Schedule;

// Mark digital product codes that have passed their expiry date.
// Runs nightly at 03:00 server time.
Schedule::command(MarkExpiredDigitalCodesCommand::class)->dailyAt('03:00');

// Fetch latest exchange rates for JOD, SAR, AED against USD.
Schedule::command(SyncExchangeRatesCommand::class)->dailyAt('00:00');

// Refresh mapped supplier cost prices from live API data.
Schedule::job(new SyncSupplierMappingPricesJob)->dailyAt('01:00');

// Ping all active suppliers to monitor health/availability.
Schedule::job(new SupplierHealthCheckJob)->everyFiveMinutes();

// Supplier stock sync (fetch_stock) is manual-only via admin "Sync Prices" — not scheduled.

// Auto-release escrows past their release deadline (no active dispute).
Schedule::job(new AutoReleaseEscrowJob)->hourly();

// Store Horizon metrics snapshots for the dashboard graphs.
Schedule::command('horizon:snapshot')->everyFiveMinutes();
