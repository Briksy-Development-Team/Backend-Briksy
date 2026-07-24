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
            'generated_id' => $this->generated_id,
            'display_id' => $this->generated_id ?: $this->id,
            'referral_code' => $this->referral_code,
            'referred_by_organization_id' => $this->referred_by_organization_id,
            'referred_by_name' => $this->whenLoaded('referredByOrganization', fn () => $this->referredByOrganization?->name),
            'referred_organizations_count' => $this->referred_organizations_count ?? $this->referredOrganizations()->count(),
            'name' => $this->name,
            'trading_name' => $this->trading_name,
            'entity_name' => $this->entity_name,
            'entity_type' => $this->entity_type,
            'entity_status' => $this->entity_status,
            'slug' => $this->slug,
            'abn' => $this->abn,
            'acn' => $this->acn,
            'abn_verified' => (bool) $this->abn_verified,
            'abn_verified_at' => $this->abn_verified_at?->toISOString(),
            'gst_registered' => (bool) $this->gst_registered,
            'abn_effective_from' => $this->abn_effective_from?->toDateString(),
            'business_type' => $this->business_type,
            'business_verification_status' => $this->business_verification_status,
            'plan_id' => $this->plan_id,
            'type' => $this->whenLoaded('organizationType', fn () => [
                'id' => $this->organizationType?->id,
                'name' => $this->organizationType?->name,
                'slug' => $this->organizationType?->slug,
            ]),
            'pending_properties_count' => (int) ($this->pending_properties_count ?? 0),
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'address' => $this->address,
            'state' => $this->state,
            'postcode' => $this->postcode,
            'ranking_priority' => $this->ranking_priority,
            'avg_org_rating' => $this->avg_org_rating,
            'stripe_customer_id' => $this->stripe_customer_id,
            'is_verified' => (bool) $this->is_verified,
            'status' => $this->deleted_at ? 'Inactive' : 'Active',
            'logo_url' => $this->logo_url,
            'brand_primary_color' => $this->brand_primary_color,
            'brand_secondary_color' => $this->brand_secondary_color,
            'licensed_staff_seats' => $this->licensed_staff_seats,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
