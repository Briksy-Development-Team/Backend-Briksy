<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrganizationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'trading_name' => ['nullable', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:200', 'unique:organizations,slug'],
            'abn' => ['required', 'string', 'size:11', 'unique:organizations,abn'],
            'business_type' => ['required', Rule::in(['organisation', 'company', 'solo_trader'])],
            'business_verification_status' => ['nullable', Rule::in(['pending', 'verified', 'rejected'])],
            'acn' => ['nullable', 'string', 'size:9', 'unique:organizations,acn'],
            'type_id' => ['required', 'uuid', 'exists:organization_types,id'],
            'plan_id' => ['nullable', 'uuid', 'exists:subscription_plans,id'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'state' => ['nullable', 'string', 'max:50'],
            'postcode' => ['nullable', 'string', 'max:10'],
            'ranking_priority' => ['nullable', 'integer', 'min:1'],
            'avg_org_rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'stripe_customer_id' => ['nullable', 'string', 'max:120'],
            'is_verified' => ['nullable', 'boolean'],
            'logo_url' => ['nullable', 'string', 'max:500'],
            'brand_primary_color' => ['nullable', 'string', 'max:20'],
            'brand_secondary_color' => ['nullable', 'string', 'max:20'],
            'licensed_staff_seats' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
