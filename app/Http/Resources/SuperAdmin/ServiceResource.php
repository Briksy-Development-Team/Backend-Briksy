<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'organization_type' => $this->whenLoaded('organizationType', fn (): ?array => $this->organizationType ? [
                'id' => $this->organizationType->id,
                'name' => $this->organizationType->name,
                'slug' => $this->organizationType->slug,
            ] : null),
            'service_group' => $this->whenLoaded('serviceGroup', fn (): ?array => $this->serviceGroup ? [
                'id' => $this->serviceGroup->id,
                'name' => $this->serviceGroup->name,
                'slug' => $this->serviceGroup->slug,
            ] : null),
            'organization_count' => $this->whenCounted('organizations'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

