<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrganizationUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organization = $this->route('organization');

        return [
            'name' => ['sometimes', 'string', 'max:200'],
            'trading_name' => ['nullable', 'string', 'max:200'],
            'slug' => ['sometimes', 'string', 'max:200', Rule::unique('organizations', 'slug')->ignore($organization?->id)],
            'abn' => ['sometimes', 'string', 'size:11', Rule::unique('organizations', 'abn')->ignore($organization?->id)],
            'business_type' => ['sometimes', Rule::in(['organisation', 'company', 'solo_trader'])],
            'business_verification_status' => ['nullable', Rule::in(['pending', 'verified', 'rejected'])],
            'acn' => ['nullable', 'string', 'size:9', Rule::unique('organizations', 'acn')->ignore($organization?->id)],
            'type_id' => ['sometimes', 'uuid', 'exists:organization_types,id'],
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
            'banner_url' => ['nullable', 'string', 'max:500'],
            'brand_primary_color' => ['nullable', 'string', 'max:20'],
            'brand_secondary_color' => ['nullable', 'string', 'max:20'],
            'licensed_staff_seats' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:1500'],
            'website' => ['nullable', 'url', 'max:500'],
            'social_links' => ['nullable', 'array'],
            'social_links.*' => ['nullable', 'url', 'max:500'],
            'service_areas' => ['nullable', 'array', 'max:50'],
            'service_areas.*' => ['string', 'max:100'],
            'intro_video_url' => ['nullable', 'url', 'max:1000'],
            'reel_urls' => ['nullable', 'array', 'max:10'],
            'reel_urls.*' => ['url', 'max:1000'],
        ];
    }
}
