<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminInquiryResource extends JsonResource
{
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
            'message' => $this->message,
            'seeker_name' => $this->seeker_name,
            'seeker_email' => $this->seeker_email,
            'seeker_phone' => $this->seeker_phone,
            'property_listing_id' => $this->property_listing_id,
            'property_title' => $property?->title,
            'property_address' => $property?->full_address ?: $property?->address,
            'organization_id' => $this->organization_id,
            'organization_name' => $organization?->name,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
