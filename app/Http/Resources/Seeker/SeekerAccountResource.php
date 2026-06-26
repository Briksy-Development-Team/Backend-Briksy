<?php

namespace App\Http\Resources\Seeker;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeekerAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'organization_id' => $this->organization_id,
            'id_verified' => (bool) $this->id_verified,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'roles' => $this->whenLoaded('roles', fn (): array => $this->roles->pluck('name')->values()->all()),
            'permissions' => $this->whenLoaded('roles', function (): array {
                return $this->roles
                    ->flatMap(fn ($role) => $role->permissions->pluck('name'))
                    ->unique()
                    ->values()
                    ->all();
            }),
            'permission_names' => $this->whenLoaded('directPermissions', fn (): array => $this->directPermissions->pluck('name')->values()->all()),
            'subscription' => $this->whenLoaded('organization', function () {
                return $this->organization ? [
                    'organization_id' => $this->organization->id,
                    'plan_id' => $this->organization->plan_id,
                    'plan' => $this->organization->plan ? [
                        'id' => $this->organization->plan->id,
                        'name' => $this->organization->plan->name,
                        'price' => (int) $this->organization->plan->price,
                    ] : null,
                    'status' => $this->organization->subscription_status ?? $this->organization->subscriptionStatus(),
                    'is_trial_active' => $this->organization->trial_ends_at ? now()->lessThanOrEqualTo($this->organization->trial_ends_at) : false,
                    'trial_started_at' => $this->organization->trial_started_at?->toISOString(),
                    'trial_ends_at' => $this->organization->trial_ends_at?->toISOString(),
                    'subscription_activated_at' => $this->organization->subscription_activated_at?->toISOString(),
                ] : null;
            }),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
