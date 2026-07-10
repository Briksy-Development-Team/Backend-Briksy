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
            'generated_id' => $this->generated_id,
            'display_id' => $this->generated_id ?: $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'category' => $this->category,
            'slug' => $this->slug,
            'description' => $this->description,
            'service_area' => $this->service_area,
            'service_area_geometry' => $this->service_area_geometry,
            'rate_from' => $this->rate_from !== null ? (float) $this->rate_from : null,
            'rate_to' => $this->rate_to !== null ? (float) $this->rate_to : null,
            'is_active' => (bool) $this->is_active,
            'organization_id' => $this->organization_id,
            'organization_type' => $this->whenLoaded('organizationType', fn (): ?array => $this->organizationType ? [
                'id' => $this->organizationType->id,
                'name' => $this->organizationType->name,
                'slug' => $this->organizationType->slug,
            ] : null),
            'organization' => $this->whenLoaded('organization', fn (): ?array => $this->organization ? [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
                'business_type' => $this->organization->business_type,
            ] : null),
            'organization_count' => $this->organizations_count ?? null,
            'service_group_count' => $this->service_groups_count ?? null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
