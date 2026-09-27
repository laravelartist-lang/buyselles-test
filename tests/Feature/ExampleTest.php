<?php

namespace Tests\Feature;

use App\Support\DatabaseSafety;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_application_boots_under_isolated_test_database(): void
    {
        $this->assertTrue(DatabaseSafety::usesInMemoryTestingDatabase());
        $this->assertSame('testing', config('app.env'));
    }
}
