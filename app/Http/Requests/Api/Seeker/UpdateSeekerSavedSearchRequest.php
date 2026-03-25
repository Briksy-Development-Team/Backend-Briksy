<?php

namespace App\Http\Requests\Api\Seeker;

class UpdateSeekerSavedSearchRequest extends StoreSeekerSavedSearchRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'budget_min' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'budget_max' => ['sometimes', 'nullable', 'numeric', 'min:0', 'gte:budget_min'],
            'location_json' => ['sometimes', 'nullable', 'array'],
            'property_types_json' => ['sometimes', 'nullable', 'array'],
            'filters_json' => ['sometimes', 'nullable', 'array'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
