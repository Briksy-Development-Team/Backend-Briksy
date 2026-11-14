<?php

namespace App\Http\Resources\Seeker;

use App\Models\PropertyListing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InquiryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $propertyListing = $this->relationLoaded('propertyListing') ? $this->propertyListing : null;
        $organization = $this->relationLoaded('organization') ? $this->organization : null;

        $propertySummary = $propertyListing instanceof PropertyListing ? [
            'title' => $propertyListing->title,
            'address' => $propertyListing->address,
            'full_address' => $propertyListing->full_address,
            'status' => $propertyListing->status,
            'land_area' => $propertyListing->land_area_sqm !== null ? (float) $propertyListing->land_area_sqm : null,
            'land_area_unit' => $propertyListing->land_area_unit ?: 'sqm',
            'floor_area' => $propertyListing->floor_area_sqm !== null ? (float) $propertyListing->floor_area_sqm : null,
            'floor_area_unit' => $propertyListing->floor_area_unit ?: 'sqm',
            'carpet_area' => $propertyListing->carpet_area !== null ? (float) $propertyListing->carpet_area : null,
            'carpet_area_unit' => $propertyListing->carpet_area_unit ?: 'sqm',
            'features' => $propertyListing->relationLoaded('features') ? $propertyListing->features->map(fn ($feature): array => [
                'id' => $feature->id,
                'name' => $feature->name,
                'slug' => $feature->slug,
                'value_type' => $feature->value_type ?: 'boolean',
                'quantity' => $feature->pivot?->quantity !== null ? (float) $feature->pivot->quantity : null,
                'quantity_is_minimum' => (bool) ($feature->pivot?->quantity_is_minimum ?? false),
            ])->values()->all() : [],
            'organization' => $propertyListing->organization ? [
                'name' => $propertyListing->organization->name,
                'slug' => $propertyListing->organization->slug,
                'is_verified' => (bool) $propertyListing->organization->is_verified,
                'contact' => [
                    'email' => $propertyListing->organization->contact_email,
                    'phone' => $propertyListing->organization->contact_phone,
                ],
            ] : null,
        ] : null;

        $organizationSummary = $organization ? [
            'name' => $organization->name,
            'slug' => $organization->slug,
            'is_verified' => (bool) $organization->is_verified,
            'contact' => [
                'email' => $organization->contact_email,
                'phone' => $organization->contact_phone,
            ],
        ] : null;

        return [
            'id' => $this->id,
            'reference_no' => $this->reference_no,
            'display_id' => $this->display_id,
            'lead_source' => $this->lead_source,
            'status' => $this->status,
            'email_delivery' => [
                'status' => $this->email_delivery_status,
                'recipient' => $this->email_recipient,
                'sent_at' => $this->email_sent_at?->toISOString(),
            ],
            'subject' => $this->subject,
            'interested_in' => $this->interested_in ?? [],
            'message' => $this->message,
            'seeker' => [
                'name' => $this->seeker_name,
                'email' => $this->seeker_email,
                'phone' => $this->seeker_phone,
            ],
            'property' => $propertySummary,
            'organization' => $organizationSummary,
            'latest_update' => [
                'at' => $this->updated_at?->toISOString(),
                'status' => $this->status,
                'message' => $this->status === 'new'
                    ? 'Inquiry submitted and awaiting a response.'
                    : sprintf('Inquiry status updated to %s.', $this->status),
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
