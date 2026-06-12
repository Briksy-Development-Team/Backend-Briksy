<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => (int) $this->price,
            'propertyLimit' => (int) $this->property_limit,
            'popular' => (bool) $this->popular,
            'features' => collect($this->features ?? [])->map(function ($feature): array {
                return [
                    'name' => (string) ($feature['name'] ?? ''),
                    'enabled' => (bool) ($feature['enabled'] ?? false),
                ];
            })->values()->all(),
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
