<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PartnerCatalogAssignFromSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_api_id' => ['required', 'integer', 'exists:supplier_apis,id'],
            'supplier_product_id' => ['required', 'string', 'max:255'],
            'supplier_product_name' => ['nullable', 'string', 'max:255'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'cost_currency' => ['nullable', 'string', 'size:3', 'alpha'],
            'partner_price' => ['required', 'numeric', 'min:0.0000000001'],
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],
            'is_direct_topup' => ['nullable', 'boolean'],
        ];
    }
}
