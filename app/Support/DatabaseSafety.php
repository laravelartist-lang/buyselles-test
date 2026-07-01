<?php

namespace App\Support;

use RuntimeException;

class DatabaseSafety
{
    /**
     * @return list<string>
     */
    public static function destructiveDatabaseCommands(): array
    {
        return [
            'migrate:fresh',
            'migrate:refresh',
            'db:wipe',
        ];
    }

    public static function isDestructiveDatabaseCommand(?string $command): bool
    {
        return in_array($command, self::destructiveDatabaseCommands(), true);
    }

    public static function assertDestructiveDatabaseCommandAllowed(?string $command = null): void
    {
        if (self::usesInMemoryTestingDatabase()) {
            return;
        }

        if (filter_var(env('ALLOW_DESTRUCTIVE_DB', false), FILTER_VALIDATE_BOOL)) {
            return;
        }

        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if ($connection === 'sqlite' && $database === ':memory:') {
            return;
        }

        $label = $command ?? 'database command';

        throw new RuntimeException(
            "Blocked destructive {$label} on database [{$connection}:{$database}]. ".
            'Use sqlite :memory: for tests, or set ALLOW_DESTRUCTIVE_DB=true in .env only when you intentionally want to wipe this database.'
        );
    }

    public static function assertTestsUseInMemoryDatabase(): void
    {
        if (! app()->environment('testing')) {
            throw new RuntimeException('This guard can only be used while running tests.');
        }

        if (! self::usesInMemoryTestingDatabase()) {
            throw new RuntimeException(
                'Tests must use sqlite :memory:. Run `composer test` or `php artisan config:clear` before `php artisan test`. '.
                'Never run tests against your development MySQL database.'
            );
        }
    }

    public static function usesInMemoryTestingDatabase(): bool
    {
        if (! app()->environment('testing')) {
            return false;
        }

        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        return $connection === 'sqlite' && $database === ':memory:';
    }
}
