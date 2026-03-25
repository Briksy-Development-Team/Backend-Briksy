<?php

namespace App\Http\Requests\Api\Seeker;

use Illuminate\Foundation\Http\FormRequest;

class StoreInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'uuid', 'exists:organizations,id'],
            'property_listing_id' => ['nullable', 'uuid', 'exists:property_listings,id'],
            'staff_id' => ['nullable', 'uuid', 'exists:users,id'],
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
            'seeker_name' => ['required_without:user_id', 'nullable', 'string', 'max:120'],
            'seeker_email' => ['required_without:user_id', 'nullable', 'email', 'max:150'],
            'seeker_phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
