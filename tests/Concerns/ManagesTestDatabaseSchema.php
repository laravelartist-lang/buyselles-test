<?php

namespace Tests\Concerns;

use App\Support\DatabaseSafety;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait ManagesTestDatabaseSchema
{
    protected function usesDestructiveDatabaseSchemaChanges(): bool
    {
        return true;
    }

    protected function recreateTable(string $table, callable $definition): void
    {
        DatabaseSafety::assertTestsUseInMemoryDatabase();

        Schema::dropIfExists($table);
        Schema::create($table, function (Blueprint $blueprint) use ($definition): void {
            $definition($blueprint);
        });
    }
}
