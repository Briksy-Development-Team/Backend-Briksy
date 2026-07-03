<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DynamicIdSettingStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entity_type' => ['required', 'string', 'max:100', 'unique:dynamic_id_settings,entity_type'],
            'prefix' => ['nullable', 'string', 'max:50'],
            'separator' => ['nullable', 'string', 'max:10'],
            'include_year' => ['sometimes', 'boolean'],
            'include_month' => ['sometimes', 'boolean'],
            'number_padding' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'starting_number' => ['sometimes', 'integer', 'min:1'],
            'current_number' => ['sometimes', 'integer', 'min:0'],
            'reset_frequency' => ['sometimes', Rule::in(['none', 'monthly', 'yearly'])],
            'last_reset_at' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
