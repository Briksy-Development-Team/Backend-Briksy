<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminPropertyListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'generated_id' => $this->generated_id,
            'display_id' => $this->generated_id ?: $this->id,
            'title' => $this->title,
            'status' => $this->status,
            'description' => $this->description,
            'address' => $this->address,
            'address_line_1' => $this->address_line_1,
            'address_line_2' => $this->address_line_2,
            'full_address' => $this->full_address,
            'formatted_address' => $this->formatted_address,
            'place_id' => $this->place_id,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'rating' => (float) $this->avg_prop_rating,
            'suburb' => $this->suburb,
            'state' => $this->state,
            'postcode' => $this->postcode,
            'country' => $this->country,
            'location_verified' => (bool) $this->location_verified,
            'organization_id' => $this->org_id,
            'property_type_id' => $this->property_type_id,
            'property_type' => $this->whenLoaded('propertyType', function (): ?array {
                return $this->propertyType ? [
                    'id' => $this->propertyType?->id,
                    'name' => $this->propertyType?->name,
                    'slug' => $this->propertyType?->slug,
                ] : null;
            }),
            'images' => $this->whenLoaded('media', function (): array {
                return $this->media
                    ->where('media_type', 'image')
                    ->map(fn ($media): array => [
                        'id' => $media->id,
                        'url' => $media->file_url,
                        'is_primary' => (bool) $media->is_primary,
                        'sort_order' => (int) $media->sort_order,
                    ])
                    ->values()
                    ->all();
            }),
            'videos' => $this->whenLoaded('media', function (): array {
                return $this->media
                    ->where('media_type', 'video')
                    ->map(fn ($media): array => [
                        'id' => $media->id,
                        'url' => $media->file_url,
                        'is_primary' => (bool) $media->is_primary,
                        'sort_order' => (int) $media->sort_order,
                    ])
                    ->values()
                    ->all();
            }),
            'organization' => $this->whenLoaded('organization', function (): array {
                return [
                    'id' => $this->organization?->id,
                    'name' => $this->organization?->name,
                    'slug' => $this->organization?->slug,
                    'is_verified' => (bool) $this->organization?->is_verified,
                ];
            }),
            'creator' => $this->whenLoaded('creator', function (): array {
                return [
                    'id' => $this->creator?->id,
                    'name' => $this->creator?->name,
                    'email' => $this->creator?->email,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
