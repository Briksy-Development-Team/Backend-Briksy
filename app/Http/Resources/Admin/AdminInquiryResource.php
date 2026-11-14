<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminInquiryResource extends JsonResource
{
    private const INTEREST_LABELS = [
        'inspection' => 'Booking an inspection',
        'property_information' => 'Property information',
        'price_sale_information' => 'Price / sale information',
        'contract_section_32' => 'Contract / Section 32',
        'making_an_offer' => 'Making an offer',
        'other' => 'Other',
    ];

    public function toArray(Request $request): array
    {
        $property = $this->whenLoaded('propertyListing', fn () => $this->propertyListing);
        $organization = $this->whenLoaded('organization', fn () => $this->organization);

        return [
            'id' => $this->id,
            'reference_no' => $this->reference_no,
            'display_id' => $this->display_id,
            'lead_source' => $this->lead_source,
            'status' => $this->status,
            'subject' => $this->subject,
            'interested_in' => collect($this->interested_in ?? [])->map(fn (string $value): string => self::INTEREST_LABELS[$value] ?? $value)->values()->all(),
            'message' => $this->message,
            'seeker_name' => $this->seeker_name,
            'seeker_email' => $this->seeker_email,
            'seeker_phone' => $this->seeker_phone,
            'property_listing_id' => $this->property_listing_id,
            'property_title' => $property?->title,
            'property_address' => $property?->full_address ?: $property?->address,
            'property_areas' => $property ? [
                'land_area' => $property->land_area_sqm !== null ? (float) $property->land_area_sqm : null,
                'land_area_unit' => $property->land_area_unit ?: 'sqm',
                'floor_area' => $property->floor_area_sqm !== null ? (float) $property->floor_area_sqm : null,
                'floor_area_unit' => $property->floor_area_unit ?: 'sqm',
                'carpet_area' => $property->carpet_area !== null ? (float) $property->carpet_area : null,
                'carpet_area_unit' => $property->carpet_area_unit ?: 'sqm',
            ] : null,
            'property_features' => $property && $property->relationLoaded('features') ? $property->features->map(fn ($feature): array => [
                'id' => $feature->id,
                'name' => $feature->name,
                'slug' => $feature->slug,
                'value_type' => $feature->value_type ?: 'boolean',
                'quantity' => $feature->pivot?->quantity !== null ? (float) $feature->pivot->quantity : null,
                'quantity_is_minimum' => (bool) ($feature->pivot?->quantity_is_minimum ?? false),
            ])->values()->all() : [],
            'organization_id' => $this->organization_id,
            'organization_name' => $organization?->name,
            'plan_id' => $this->plan_id,
            'plan_name' => $this->whenLoaded('plan', fn () => $this->plan?->name),
            'company_name' => $this->company_name,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
