<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'generated_id' => $this->generated_id,
            'display_id' => $this->display_id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'email' => $this->email,
            'mobile_number' => $this->mobile_number,
            'admin_notes' => $this->admin_notes,
            'organization_id' => $this->organization_id,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'mobile_verified_at' => $this->mobile_verified_at?->toISOString(),
            'roles' => $this->whenLoaded('roles', fn (): array => $this->roles->pluck('name')->values()->all()),
            'permissions' => $this->getAllPermissions()->pluck('name')->values()->all(),
            'status' => $this->deleted_at ? 'inactive' : 'active',
            'created_at' => $this->created_at?->toISOString(),
            'inquiries' => $this->whenLoaded('inquiries', fn (): array => $this->inquiries->map(fn ($inquiry): array => [
                'id' => $inquiry->id,
                'reference_no' => $inquiry->reference_no,
                'subject' => $inquiry->subject,
                'status' => $inquiry->status,
                'lead_source' => $inquiry->lead_source,
                'property_title' => $inquiry->propertyListing?->title,
                'organization_name' => $inquiry->organization?->name,
                'created_at' => $inquiry->created_at?->toISOString(),
            ])->values()->all()),
        ];
    }
}
