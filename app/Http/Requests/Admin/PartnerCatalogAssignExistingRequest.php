<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PartnerCatalogAssignExistingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'partner_price' => ['required', 'numeric', 'min:0.0000000001'],
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
