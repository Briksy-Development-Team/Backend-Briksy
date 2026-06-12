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
            'name' => $this->name,
            'display_name' => $this->display_name,
            'email' => $this->email,
            'mobile_number' => $this->mobile_number,
            'organization_id' => $this->organization_id,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'mobile_verified_at' => $this->mobile_verified_at?->toISOString(),
            'roles' => $this->whenLoaded('roles', fn (): array => $this->roles->pluck('name')->values()->all()),
            'permissions' => $this->whenLoaded('roles', function (): array {
                return $this->roles
                    ->flatMap(fn ($role) => $role->permissions->pluck('name'))
                    ->unique()
                    ->values()
                    ->all();
            }),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
