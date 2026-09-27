<?php

namespace Tests;

use App\Support\DatabaseSafety;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use RuntimeException;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        if ($app->environment('testing') && ! DatabaseSafety::usesInMemoryTestingDatabase()) {
            throw new RuntimeException(
                'Refusing to boot tests on a non-isolated database. '.
                'Use `composer test` or `vendor/bin/phpunit` (sqlite :memory:). '.
                'Current default connection: '.config('database.default').'.'
            );
        }

        return $app;
    }
}
