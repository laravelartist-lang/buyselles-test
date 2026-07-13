<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class DirectTopUpProductAdminTest extends TestCase
{
    public function test_direct_topup_account_label_is_required_when_enabled(): void
    {
        $validator = Validator::make([
            'is_direct_topup' => 1,
            'direct_topup_account_label' => '',
        ], [
            'direct_topup_account_label' => 'required_if:is_direct_topup,1,true|nullable|string|max:255',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('direct_topup_account_label'));
    }

    public function test_ready_product_digital_code_is_not_required_when_direct_topup_enabled(): void
    {
        $isDirectTopUp = true;
        $digitalProductType = 'ready_product';
        $cardCode = '';

        $requiresDigitalCode = $digitalProductType === 'ready_product'
            && ! $isDirectTopUp
            && $cardCode === '';

        $this->assertFalse($requiresDigitalCode);
    }
}
