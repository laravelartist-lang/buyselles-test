<?php

namespace Tests\Unit;

use App\Support\DatabaseSafety;
use Tests\TestCase;

class DatabaseSafetyTest extends TestCase
{
    public function test_tests_run_on_sqlite_memory_database(): void
    {
        $this->assertTrue(DatabaseSafety::usesInMemoryTestingDatabase());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_destructive_database_commands_are_blocked_on_mysql_without_override(): void
    {
        config(['database.default' => 'mysql']);
        config(['database.connections.mysql.database' => 'buyselles']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Blocked destructive migrate:fresh');

        DatabaseSafety::assertDestructiveDatabaseCommandAllowed('migrate:fresh');
    }
}
