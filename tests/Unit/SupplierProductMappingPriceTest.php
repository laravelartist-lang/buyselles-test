<?php

namespace Tests\Unit;

use App\Models\SupplierProductDenomination;
use App\Models\SupplierProductMapping;
use Tests\TestCase;

class SupplierProductMappingPriceTest extends TestCase
{
    public function test_starting_display_price_uses_minimum_for_customizable_variable_product(): void
    {
        $mapping = new SupplierProductMapping([
            'is_customizable' => true,
            'cost_price' => 0,
            'markup_type' => 'percent',
            'markup_value' => 10,
            'min_amount' => 25,
            'max_amount' => 500,
        ]);

        $variable = new SupplierProductDenomination([
            'type' => 'variable',
            'min_face_value' => 50,
            'max_face_value' => 500,
        ]);

        $mapping->setRelation('activeDenominations', collect([$variable]));

        $this->assertSame(50.0, $mapping->getStartingDisplayPrice());
    }

    public function test_starting_display_price_uses_first_fixed_denomination_when_available(): void
    {
        $mapping = new SupplierProductMapping([
            'is_customizable' => true,
            'cost_price' => 0,
            'markup_type' => 'flat',
            'markup_value' => 2,
        ]);

        $fixed = new SupplierProductDenomination([
            'type' => 'fixed',
            'face_value' => 10,
        ]);
        $fixed->setRelation('mapping', $mapping);

        $mapping->setRelation('activeDenominations', collect([$fixed]));

        $this->assertSame(12.0, $mapping->getStartingDisplayPrice());
    }

    public function test_calculate_sell_price_still_uses_cost_for_non_customizable_products(): void
    {
        $mapping = new SupplierProductMapping([
            'is_customizable' => false,
            'cost_price' => 20,
            'markup_type' => 'percent',
            'markup_value' => 10,
        ]);

        $this->assertSame(22.0, $mapping->getStartingDisplayPrice());
    }
}
