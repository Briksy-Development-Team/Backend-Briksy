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
                    'value' => array_key_exists('value', $feature) && $feature['value'] !== null ? (int) $feature['value'] : null,
                ];
            })->values()->all(),
            'permissions' => collect($this->permissions ?? [])->values()->all(),
            'is_active' => (bool) $this->is_active,
            'is_current' => (bool) ($request->user()?->organization?->plan_id === $this->id),
            'staff_seat_limit' => (int) ($this->staff_seat_limit ?? 0),
            'has_visitor_analytics' => (bool) ($this->has_visitor_analytics ?? false),
            'ranking_priority' => (int) ($this->ranking_priority ?? 0),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
