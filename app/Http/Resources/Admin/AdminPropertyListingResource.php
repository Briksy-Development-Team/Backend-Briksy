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
            'rating' => (float) $this->avg_prop_rating,
            'suburb' => $this->suburb,
            'postcode' => $this->postcode,
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
