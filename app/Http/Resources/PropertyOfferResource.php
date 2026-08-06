<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyOfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'property_listing_id' => $this->property_listing_id,
            'created_by' => $this->created_by,
            'title' => $this->title,
            'tag_label' => $this->tag_label,
            'summary' => $this->summary,
            'description' => $this->description,
            'highlights' => $this->highlights ?? [],
            'terms' => $this->terms,
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'property_listing' => $this->whenLoaded('propertyListing', function (): ?array {
                return $this->propertyListing ? [
                    'id' => $this->propertyListing->id,
                    'generated_id' => $this->propertyListing->generated_id,
                    'title' => $this->propertyListing->title,
                    'address' => $this->propertyListing->formatted_address ?? $this->propertyListing->full_address ?? $this->propertyListing->address,
                ] : null;
            }),
            'creator' => $this->whenLoaded('creator', function (): ?array {
                return $this->creator ? [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ] : null;
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
