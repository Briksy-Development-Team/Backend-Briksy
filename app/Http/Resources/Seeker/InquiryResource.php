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
            'subject' => $this->subject,
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
