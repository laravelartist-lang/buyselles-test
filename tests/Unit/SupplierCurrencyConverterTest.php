<?php

namespace Tests\Unit;

use App\Models\BusinessSetting;
use App\Models\Currency;
use App\Services\Supplier\SupplierCurrencyConverter;
use Illuminate\Database\Schema\Blueprint;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class SupplierCurrencyConverterTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    private SupplierCurrencyConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recreateTable('currencies', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('symbol')->nullable();
            $table->string('code', 10);
            $table->decimal('exchange_rate', 14, 6)->default(1);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        $this->recreateTable('business_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $usd = Currency::query()->create([
            'name' => 'USD',
            'symbol' => '$',
            'code' => 'USD',
            'exchange_rate' => 1,
            'status' => true,
        ]);

        Currency::query()->create([
            'name' => 'Jordanian Dinar',
            'symbol' => 'JOD',
            'code' => 'JOD',
            'exchange_rate' => 0.709,
            'status' => true,
        ]);

        BusinessSetting::query()->create(['type' => 'currency_model', 'value' => 'multi_currency']);
        BusinessSetting::query()->create(['type' => 'system_default_currency', 'value' => (string) $usd->id]);
        BusinessSetting::query()->create(['type' => 'decimal_point_settings', 'value' => '2']);

        $this->converter = app(SupplierCurrencyConverter::class);
    }

    public function test_converts_jod_to_usd(): void
    {
        $result = $this->converter->toUsd(1.62, 'JOD');

        $this->assertEqualsWithDelta(2.28, $result, 0.01);
    }

    public function test_convert_price_with_source_currency_returns_usd(): void
    {
        $result = $this->converter->convertPrice(1.709, 'JOD');

        $this->assertSame('USD', $result['currency']);
        $this->assertEqualsWithDelta(2.41, $result['price'], 0.01);
    }

    public function test_convert_price_without_source_currency_passthrough(): void
    {
        $result = $this->converter->convertPrice(9.99, null);

        $this->assertSame('USD', $result['currency']);
        $this->assertSame(9.99, $result['price']);
    }

    public function test_convert_between_jod_and_usd(): void
    {
        $usd = $this->converter->convertBetween(1.62, 'JOD', 'USD');
        $backToJod = $this->converter->convertBetween($usd, 'USD', 'JOD');

        $this->assertEqualsWithDelta(1.62, $backToJod, 0.02);
    }
}
