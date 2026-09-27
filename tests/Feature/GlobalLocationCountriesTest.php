<?php

namespace Tests\Feature;

use App\Models\LocationCountry;
use Database\Seeders\LocationCountrySeeder;
use Illuminate\Database\Schema\Blueprint;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class GlobalLocationCountriesTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recreateTable('location_countries', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_location_countries_can_be_seeded(): void
    {
        $this->seed(LocationCountrySeeder::class);

        $this->assertGreaterThan(200, LocationCountry::count());
    }

    public function test_all_seeded_countries_are_active(): void
    {
        $this->seed(LocationCountrySeeder::class);

        $totalCount = LocationCountry::count();
        $activeCount = LocationCountry::where('is_active', true)->count();

        $this->assertEquals($totalCount, $activeCount);
    }
}
