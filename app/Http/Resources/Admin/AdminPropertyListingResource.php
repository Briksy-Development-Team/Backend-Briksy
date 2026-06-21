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
            'title' => $this->title,
            'status' => $this->status,
            'description' => $this->description,
            'address' => $this->address,
            'full_address' => $this->full_address,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'rating' => (float) $this->avg_prop_rating,
            'suburb' => $this->suburb,
            'postcode' => $this->postcode,
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
