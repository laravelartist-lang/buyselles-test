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

    public function test_direct_topup_micro_unit_cost_preserves_precision_in_sell_price(): void
    {
        $mapping = new SupplierProductMapping([
            'is_customizable' => false,
            'is_direct_topup' => true,
            'cost_price' => 0.001062834,
            'markup_type' => 'percent',
            'markup_value' => 0,
        ]);

        $this->assertEqualsWithDelta(0.001062834, $mapping->calculateSellPrice(), 0.0000000001);
        $this->assertEqualsWithDelta(0.001062834, $mapping->getStartingDisplayPrice(), 0.0000000001);
        $this->assertSame(10, $mapping->resolvePriceDecimalPlaces());
    }

    public function test_resolve_supplier_face_value_never_uses_wholesale_cost(): void
    {
        $mapping = new SupplierProductMapping([
            'cost_price' => 4.8,
            'min_amount' => null,
            'is_customizable' => false,
        ]);
        $mapping->setRelation('activeDenominations', collect());

        $this->assertNull($mapping->resolveSupplierFaceValue());
    }

    public function test_resolve_supplier_face_value_uses_fixed_denomination(): void
    {
        $mapping = new SupplierProductMapping(['cost_price' => 4.8]);

        $denomination = new SupplierProductDenomination([
            'type' => 'fixed',
            'face_value' => 10,
        ]);

        $this->assertSame(10.0, $mapping->resolveSupplierFaceValue(null, $denomination));
    }

    public function test_resolve_supplier_face_value_uses_custom_amount_for_variable_denomination(): void
    {
        $mapping = new SupplierProductMapping(['cost_price' => 4.8]);

        $denomination = new SupplierProductDenomination([
            'type' => 'variable',
            'min_face_value' => 5,
            'max_face_value' => 500,
        ]);

        $this->assertSame(25.0, $mapping->resolveSupplierFaceValue(25, $denomination));
    }

    public function test_resolve_supplier_face_value_uses_mapping_min_amount_when_no_custom_amount(): void
    {
        $mapping = new SupplierProductMapping([
            'cost_price' => 4.8,
            'min_amount' => 5,
        ]);

        $this->assertSame(5.0, $mapping->resolveSupplierFaceValue());
    }

    public function test_resolve_default_denomination_matches_mapping_supplier_product_id(): void
    {
        $mapping = new SupplierProductMapping([
            'is_customizable' => false,
            'supplier_product_id' => '3313615',
        ]);

        $match = new SupplierProductDenomination([
            'type' => 'fixed',
            'supplier_product_id' => '3313615',
            'face_value' => 1,
        ]);
        $other = new SupplierProductDenomination([
            'type' => 'fixed',
            'supplier_product_id' => '3313616',
            'face_value' => 5,
        ]);

        $mapping->setRelation('activeDenominations', collect([$other, $match]));

        $this->assertSame($match, $mapping->resolveDefaultDenomination());
        $this->assertSame(1.0, $mapping->resolveSupplierFaceValue());
    }

    public function test_resolve_default_denomination_uses_sole_fixed_when_no_sku_match(): void
    {
        $mapping = new SupplierProductMapping([
            'is_customizable' => false,
            'supplier_product_id' => '999',
        ]);

        $only = new SupplierProductDenomination([
            'type' => 'fixed',
            'supplier_product_id' => '111',
            'face_value' => 10,
        ]);

        $mapping->setRelation('activeDenominations', collect([$only]));

        $this->assertSame($only, $mapping->resolveDefaultDenomination());
    }

    public function test_resolve_default_denomination_returns_null_when_multiple_fixed_without_sku_match(): void
    {
        $mapping = new SupplierProductMapping([
            'is_customizable' => false,
            'supplier_product_id' => '999',
        ]);

        $mapping->setRelation('activeDenominations', collect([
            new SupplierProductDenomination(['type' => 'fixed', 'supplier_product_id' => 'a', 'face_value' => 1]),
            new SupplierProductDenomination(['type' => 'fixed', 'supplier_product_id' => 'b', 'face_value' => 2]),
        ]));

        $this->assertNull($mapping->resolveDefaultDenomination());
    }

    public function test_resolve_default_denomination_returns_null_when_selection_required(): void
    {
        $mapping = new SupplierProductMapping(['is_customizable' => true]);

        $mapping->setRelation('activeDenominations', collect([
            new SupplierProductDenomination(['type' => 'fixed', 'face_value' => 10]),
        ]));

        $this->assertNull($mapping->resolveDefaultDenomination());
    }
}
