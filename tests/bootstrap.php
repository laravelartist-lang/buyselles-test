<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| PHPUnit Bootstrap
|--------------------------------------------------------------------------
|
| Force an isolated in-memory sqlite database before Laravel boots so tests
| never touch the local MySQL development database, even when config cache
| exists or .env points at MySQL.
|
*/

$cachedConfig = __DIR__.'/../bootstrap/cache/config.php';

if (is_file($cachedConfig)) {
    unlink($cachedConfig);
}

$testingEnvironment = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'CACHE_DRIVER' => 'array',
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array',
    'BROADCAST_DRIVER' => 'log',
];

// Prevent .env / shell MySQL credentials from overriding PHPUnit isolation.
$blockedDatabaseEnvKeys = [
    'DATABASE_URL',
    'DB_HOST',
    'DB_PORT',
    'DB_USERNAME',
    'DB_PASSWORD',
    'DB_SOCKET',
    'MYSQL_DATABASE',
    'MYSQL_HOST',
];

foreach ($blockedDatabaseEnvKeys as $key) {
    putenv($key);
    unset($_ENV[$key], $_SERVER[$key]);
}

foreach ($testingEnvironment as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require __DIR__.'/../vendor/autoload.php';
