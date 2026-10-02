<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrganizationTypeUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organizationType = $this->route('organizationType');

        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'slug' => ['sometimes', 'string', 'max:100', Rule::unique('organization_types', 'slug')->ignore($organizationType?->id)],
            'module' => ['sometimes', 'nullable', 'string', 'max:100'],
            'display_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'capability_profile' => ['sometimes', 'nullable', 'string', 'max:100'],
            'plan_family' => ['sometimes', 'nullable', 'string', 'in:property_owner,trades_professional,buyers_agent,builders'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
