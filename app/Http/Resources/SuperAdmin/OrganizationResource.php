<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'abn' => $this->abn,
            'acn' => $this->acn,
            'plan_id' => $this->plan_id,
            'type' => $this->whenLoaded('organizationType', fn () => [
                'id' => $this->organizationType?->id,
                'name' => $this->organizationType?->name,
                'slug' => $this->organizationType?->slug,
            ]),
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'ranking_priority' => $this->ranking_priority,
            'avg_org_rating' => $this->avg_org_rating,
            'stripe_customer_id' => $this->stripe_customer_id,
            'is_verified' => (bool) $this->is_verified,
            'logo_url' => $this->logo_url,
            'brand_primary_color' => $this->brand_primary_color,
            'brand_secondary_color' => $this->brand_secondary_color,
            'licensed_staff_seats' => $this->licensed_staff_seats,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}