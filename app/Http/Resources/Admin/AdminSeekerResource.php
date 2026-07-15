<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminSeekerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'generated_id' => $this->generated_id,
            'display_id' => $this->display_id,
            'full_name' => $this->name,
            'email_address' => $this->email,
            'status' => $this->deleted_at === null ? 'Active' : 'Inactive',
            'last_login' => null,
            'current_login' => null,
            'age' => null,
            'gender' => null,
            'location' => $this->seekerProfile?->current_postcode,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
