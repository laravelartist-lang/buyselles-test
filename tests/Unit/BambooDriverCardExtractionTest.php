<?php

namespace Tests\Unit;

use App\Models\SupplierApi;
use App\Services\Supplier\Drivers\BambooDriver;
use ReflectionMethod;
use Tests\TestCase;

class BambooDriverCardExtractionTest extends TestCase
{
    /**
     * @return array{code: string, pin?: string|null, serial_number?: string|null, expiry_date?: string|null}|null
     */
    private function buildRecord(string $cardCode, string $pin, string $serial = '', ?string $expiry = null): ?array
    {
        $driver = (new BambooDriver)->configure(new SupplierApi([
            'base_url' => 'https://api.bamboocardportal.com',
            'settings' => [],
        ]));

        $method = new ReflectionMethod(BambooDriver::class, 'buildBambooCardRecord');

        return $method->invoke($driver, $cardCode, $pin, $serial, $expiry);
    }

    public function test_portal_url_strips_wrapper_and_keeps_pin_and_serial_separate(): void
    {
        $record = $this->buildRecord(
            cardCode: 'https://bamboocardportal.com/viewcard?Code=f69f39996d2d415d973045e16131c8f2',
            pin: '930',
            serial: 'b8dc0675-2ad9-4282-a33c-804deb982b7d',
        );

        $this->assertNotNull($record);
        $this->assertSame('f69f39996d2d415d973045e16131c8f2', $record['code']);
        $this->assertSame('930', $record['pin']);
        $this->assertSame('b8dc0675-2ad9-4282-a33c-804deb982b7d', $record['serial_number']);
    }

    public function test_portal_url_without_pin_extracts_code_token_only(): void
    {
        $record = $this->buildRecord(
            cardCode: 'https://bamboocardportal.com/viewcard?Code=cb05747ed9fe497b92c80f10cf8406d7',
            pin: '',
        );

        $this->assertNotNull($record);
        $this->assertSame('cb05747ed9fe497b92c80f10cf8406d7', $record['code']);
        $this->assertArrayNotHasKey('pin', $record);
    }

    public function test_standard_bamboo_response_keeps_card_code_pin_and_serial_separate(): void
    {
        $record = $this->buildRecord(
            cardCode: 'cb05747ed9fe497b92c80f10cf8406d7',
            pin: '392',
            serial: '4005c08f-1aeb-4527-8eb0-ccd1c745f467',
            expiry: '2025-05-16T04:36:35.3922677',
        );

        $this->assertNotNull($record);
        $this->assertSame('cb05747ed9fe497b92c80f10cf8406d7', $record['code']);
        $this->assertSame('392', $record['pin']);
        $this->assertSame('2025-05-16', $record['expiry_date']);
    }
}
