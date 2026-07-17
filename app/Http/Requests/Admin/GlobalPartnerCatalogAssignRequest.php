<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class GlobalPartnerCatalogAssignRequest extends FormRequest
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
            'reseller_key_id' => ['required', 'integer', 'exists:reseller_api_keys,id'],
            'partner_price' => ['nullable', 'numeric', 'min:0.0000000001'],
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
