<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class OrganizationTypeStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'unique:organization_types,slug'],
            'module' => ['nullable', 'string', 'max:100'],
            'display_name' => ['nullable', 'string', 'max:100'],
            'capability_profile' => ['nullable', 'string', 'max:100'],
            'plan_family' => ['nullable', 'string', 'in:property_owner,trades_professional,buyers_agent,builders'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
