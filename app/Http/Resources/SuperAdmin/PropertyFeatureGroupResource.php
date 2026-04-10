<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyFeatureGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sort_order' => $this->sort_order,
            'features' => $this->whenLoaded('features', fn (): array => $this->features
                ->map(fn ($feature): array => [
                    'id' => $feature->id,
                    'name' => $feature->name,
                    'slug' => $feature->slug,
                    'sort_order' => $feature->sort_order,
                ])->values()->all()),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

