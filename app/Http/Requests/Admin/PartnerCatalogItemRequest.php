<?php

namespace App\Http\Requests\Admin;

use App\Models\PartnerCatalogItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PartnerCatalogItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'partner_price' => ['nullable', 'numeric', 'min:0.0000000001'],
            'variable_price_type' => [
                'nullable',
                Rule::in([
                    PartnerCatalogItem::VARIABLE_PRICE_PERCENT,
                    PartnerCatalogItem::VARIABLE_PRICE_FLAT,
                ]),
            ],
            'variable_price_value' => [
                'nullable',
                'numeric',
                'min:0',
                'required_with:variable_price_type',
            ],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'is_active' => ['nullable', 'boolean'],
            'denomination_prices' => ['nullable', 'array'],
            'denomination_prices.*.denomination_id' => [
                'required',
                'integer',
                'distinct',
                'exists:supplier_product_denominations,id',
            ],
            'denomination_prices.*.partner_price' => [
                'required',
                'numeric',
                'min:0.0000000001',
            ],
        ];
    }
}
