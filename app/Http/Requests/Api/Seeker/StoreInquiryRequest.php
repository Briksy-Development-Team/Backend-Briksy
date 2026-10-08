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
            'lead_source' => ['nullable', 'string', 'max:80'],
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'subject' => ['required', 'string', 'max:150'],
            'interested_in' => ['required', 'array', 'min:1'],
            'interested_in.*' => ['string', 'in:inspection,property_information,price_sale_information,contract_section_32,making_an_offer,other'],
            'message' => ['nullable', 'string', 'max:5000'],
            'seeker_name' => ['required', 'string', 'max:120'],
            'seeker_email' => ['required', 'email', 'max:150'],
            'seeker_phone' => ['required', 'string', 'max:30'],
        ];
    }
}
