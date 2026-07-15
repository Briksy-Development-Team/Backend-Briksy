<?php

namespace App\Http\Resources\Seeker;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InquiryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_no' => $this->reference_no,
            'display_id' => $this->display_id,
            'organization_id' => $this->organization_id,
            'property_listing_id' => $this->property_listing_id,
            'staff_id' => $this->staff_id,
            'lead_source' => $this->lead_source,
            'status' => $this->status,
            'subject' => $this->subject,
            'message' => $this->message,
            'seeker' => [
                'name' => $this->seeker_name,
                'email' => $this->seeker_email,
                'phone' => $this->seeker_phone,
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
