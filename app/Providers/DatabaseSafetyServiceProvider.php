<?php

namespace App\Providers;

use App\Support\DatabaseSafety;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class DatabaseSafetyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            if (! DatabaseSafety::isDestructiveDatabaseCommand($event->command)) {
                return;
            }

            DatabaseSafety::assertDestructiveDatabaseCommandAllowed($event->command);
        });
    }
}
