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
            'image' => $this->logo_url ?: '/media/avatars/blank.png',
            'name' => $this->name,
            'email' => $this->contact_email,
            'status' => $this->deleted_at === null ? 'Active' : 'Blocked',
            'abn' => $this->abn,
            'acn' => $this->acn,
            'is_verified' => (bool) $this->is_verified,
            'licensed_staff_seats' => $this->licensed_staff_seats,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
