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
            'plan_family' => $this->plan_family ?? 'property_owner',
            'description' => $this->description,
            'price' => (int) $this->price,
            'monthly_price' => $this->monthly_price !== null ? (float) $this->monthly_price : null,
            'yearly_price' => $this->yearly_price !== null ? (float) $this->yearly_price : null,
            'currency' => $this->currency ?? 'AUD',
            'billing_enabled' => (bool) ($this->billing_enabled ?? true),
            'trial_days' => $this->trial_days !== null ? (int) $this->trial_days : null,
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
            'addons' => $this->whenLoaded('addons', fn (): array => $this->addons->map(fn ($addon): array => [
                'id' => $addon->id,
                'name' => $addon->name,
                'slug' => $addon->slug,
                'feature_key' => $addon->feature_key,
                'pricing_type' => $addon->pricing_type,
                'monthly_price' => $addon->monthly_price !== null ? (float) $addon->monthly_price : null,
                'yearly_price' => $addon->yearly_price !== null ? (float) $addon->yearly_price : null,
                'one_time_price' => $addon->one_time_price !== null ? (float) $addon->one_time_price : null,
                'currency' => $addon->currency,
                'is_active' => (bool) $addon->is_active,
                'pivot' => [
                    'included_quantity' => $addon->pivot?->included_quantity,
                    'is_included' => (bool) ($addon->pivot?->is_included ?? true),
                ],
            ])->values()->all()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
