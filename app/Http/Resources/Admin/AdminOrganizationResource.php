<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminOrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'generated_id' => $this->generated_id,
            'display_id' => $this->generated_id ?: $this->id,
            'image' => $this->logo_url ?: '/media/avatars/blank.png',
            'name' => $this->name,
            'referral_code' => $this->referral_code,
            'trading_name' => $this->trading_name,
            'email' => $this->contact_email,
            'status' => $this->deleted_at === null ? 'Active' : 'Blocked',
            'abn' => $this->abn,
            'acn' => $this->acn,
            'entity_name' => $this->entity_name,
            'entity_type' => $this->entity_type,
            'entity_status' => $this->entity_status,
            'abn_verified' => (bool) $this->abn_verified,
            'abn_verified_at' => $this->abn_verified_at?->toISOString(),
            'gst_registered' => (bool) $this->gst_registered,
            'abn_effective_from' => $this->abn_effective_from?->toDateString(),
            'business_type' => $this->business_type,
            'business_verification_status' => $this->business_verification_status,
            'is_verified' => (bool) $this->is_verified,
            'address' => $this->address,
            'state' => $this->state,
            'postcode' => $this->postcode,
            'licensed_staff_seats' => $this->licensed_staff_seats,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
