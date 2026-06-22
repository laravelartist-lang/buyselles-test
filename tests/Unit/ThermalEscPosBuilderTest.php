<?php

namespace Tests\Unit;

use App\Services\ThermalEscPosBuilder;
use PHPUnit\Framework\TestCase;

class ThermalEscPosBuilderTest extends TestCase
{
    private const CUT_COMMAND = "\x1D\x56\x42\x00";

    public function test_single_code_receipt_ends_with_cut_command(): void
    {
        $builder = new ThermalEscPosBuilder;

        $job = $builder->buildSingleCodeReceipt('BuySelles', 'Marketplace', [
            'productName' => 'Steam Wallet',
            'code' => 'ABCD-1234',
            'orderId' => 1001,
        ]);

        $this->assertStringEndsWith(self::CUT_COMMAND, $job);
        $this->assertStringContainsString('Code: ABCD-1234', $job);
        $this->assertStringContainsString('Order: #1001', $job);
    }

    public function test_multiple_codes_create_separate_jobs_each_with_cut(): void
    {
        $builder = new ThermalEscPosBuilder;

        $jobs = $builder->buildJobs('BuySelles', 'Marketplace', [
            [
                'productName' => 'Product A',
                'code' => 'CODE-A',
                'orderId' => 2001,
            ],
            [
                'productName' => 'Product B',
                'code' => 'CODE-B',
                'orderId' => 2001,
            ],
            [
                'productName' => 'Product C',
                'code' => 'CODE-C',
                'orderId' => 2001,
            ],
        ]);

        $this->assertCount(3, $jobs);
        $this->assertStringContainsString('Receipt 1/3', $jobs[0]);
        $this->assertStringContainsString('Receipt 2/3', $jobs[1]);
        $this->assertStringContainsString('Receipt 3/3', $jobs[2]);

        foreach ($jobs as $job) {
            $this->assertStringEndsWith(self::CUT_COMMAND, $job);
        }
    }
}
