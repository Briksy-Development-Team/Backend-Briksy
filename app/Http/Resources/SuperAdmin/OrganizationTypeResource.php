<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => $this->label,
            'slug' => $this->slug,
            'module' => $this->module,
            'display_name' => $this->display_name,
            'capability_profile' => $this->capability_profile,
            'plan_family' => $this->plan_family,
            'is_active' => (bool) ($this->is_active ?? true),
            'sort_order' => (int) ($this->sort_order ?? 0),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
