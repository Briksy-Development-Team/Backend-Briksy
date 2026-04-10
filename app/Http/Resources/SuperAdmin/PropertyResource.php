<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'org_id' => $this->org_id,
            'creator_id' => $this->creator_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'avg_prop_rating' => $this->avg_prop_rating !== null ? (float) $this->avg_prop_rating : null,
            'suburb' => $this->suburb,
            'postcode' => $this->postcode,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'property_condition' => $this->property_condition,
            'bedroom_option' => $this->bedroom_option,
            'bathroom_option' => $this->bathroom_option,
            'car_space_option' => $this->car_space_option,
            'organization' => $this->whenLoaded('organization', fn (): ?array => $this->organization ? [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
                'slug' => $this->organization->slug,
                'provider_type' => $this->organization->relationLoaded('soleTraderProfiles')
                    && $this->organization->soleTraderProfiles->isNotEmpty()
                    ? 'sole_trader'
                    : 'organization',
            ] : null),
            'property_type' => $this->whenLoaded('propertyType', fn (): ?array => $this->propertyType ? [
                'id' => $this->propertyType->id,
                'name' => $this->propertyType->name,
                'slug' => $this->propertyType->slug,
            ] : null),
            'features' => $this->whenLoaded('features', fn (): array => $this->features
                ->map(fn ($feature): array => [
                    'id' => $feature->id,
                    'name' => $feature->name,
                    'slug' => $feature->slug,
                ])->values()->all()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
