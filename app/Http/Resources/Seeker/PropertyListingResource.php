<?php

namespace App\Http\Resources\Seeker;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'address' => $this->address,
            'full_address' => $this->full_address,
            'status' => $this->status,
            'rating' => (float) $this->avg_prop_rating,
            'location' => [
                'suburb' => $this->suburb,
                'postcode' => $this->postcode,
                'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
                'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            ],
            'organization' => $this->whenLoaded('organization', function (): array {
                return [
                    'id' => $this->organization?->id,
                    'name' => $this->organization?->name,
                    'slug' => $this->organization?->slug,
                    'type' => $this->organization?->organizationType?->name,
                    'is_verified' => (bool) $this->organization?->is_verified,
                ];
            }),
            'media' => $this->whenLoaded('media', fn (): array => $this->media
                ->map(fn ($media): array => [
                    'id' => $media->id,
                    'url' => $media->file_url,
                    'type' => $media->media_type,
                    'is_primary' => (bool) $media->is_primary,
                ])->values()->all()),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
