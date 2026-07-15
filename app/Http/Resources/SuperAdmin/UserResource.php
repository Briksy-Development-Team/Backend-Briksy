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
            'organization_id' => $this->organization_id,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'mobile_verified_at' => $this->mobile_verified_at?->toISOString(),
            'roles' => $this->whenLoaded('roles', fn (): array => $this->roles->pluck('name')->values()->all()),
            'permissions' => $this->getAllPermissions()->pluck('name')->values()->all(),
            'status' => $this->deleted_at ? 'inactive' : 'active',
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
