<?php

namespace App\Http\Requests\Api\Seeker;

use Illuminate\Foundation\Http\FormRequest;

class StoreSeekerSavedSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'budget_min' => ['nullable', 'numeric', 'min:0'],
            'budget_max' => ['nullable', 'numeric', 'min:0', 'gte:budget_min'],
            'location_json' => ['nullable', 'array'],
            'property_types_json' => ['nullable', 'array'],
            'filters_json' => ['nullable', 'array'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}
