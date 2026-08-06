<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class PropertyOfferStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['nullable', 'uuid', 'exists:organizations,id'],
            'property_listing_id' => ['required', 'uuid', 'exists:property_listings,id'],
            'title' => ['required', 'string', 'max:255'],
            'tag_label' => ['nullable', 'string', 'max:100'],
            'summary' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'highlights' => ['nullable', 'array'],
            'highlights.*' => ['nullable', 'string', 'max:255'],
            'terms' => ['nullable', 'string'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
