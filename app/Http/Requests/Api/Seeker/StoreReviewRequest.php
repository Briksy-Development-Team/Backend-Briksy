<?php

namespace App\Http\Requests\Api\Seeker;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['nullable', 'uuid', 'exists:organizations,id', 'required_without:property_listing_id'],
            'property_listing_id' => ['nullable', 'uuid', 'exists:property_listings,id', 'required_without:organization_id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
