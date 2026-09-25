<?php

namespace App\Http\Resources\Seeker;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CollectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'properties_count' => (int) ($this->properties_count ?? ($this->relationLoaded('properties') ? $this->properties->count() : 0)),
            'contains_property' => (bool) ($this->contains_property ?? false),
            'properties' => $this->whenLoaded('properties', fn (): array => PropertyListingResource::collection($this->properties)->resolve($request)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
