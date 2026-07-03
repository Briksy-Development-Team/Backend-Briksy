<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'company' => $this->whenLoaded('organization', fn (): ?array => $this->organization ? [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
                'slug' => $this->organization->slug,
            ] : null),
            'plan' => $this->whenLoaded('plan', fn (): ?array => $this->plan ? [
                'id' => $this->plan->id,
                'name' => $this->plan->name,
                'monthly_price' => $this->plan->monthly_price !== null ? (float) $this->plan->monthly_price : null,
                'yearly_price' => $this->plan->yearly_price !== null ? (float) $this->plan->yearly_price : null,
                'currency' => $this->plan->currency ?? 'AUD',
            ] : null),
            'billing_cycle' => $this->billing_cycle,
            'status' => $this->status,
            'currency' => $this->currency,
            'amount' => $this->amount !== null ? (float) $this->amount : null,
            'stripe_customer_id' => $this->stripe_customer_id,
            'stripe_subscription_id' => $this->stripe_subscription_id,
            'stripe_checkout_session_id' => $this->stripe_checkout_session_id,
            'latest_invoice_id' => $this->latest_invoice_id,
            'current_period_start' => $this->current_period_start?->toISOString(),
            'current_period_end' => $this->current_period_end?->toISOString(),
            'canceled_at' => $this->canceled_at?->toISOString(),
            'payment_status' => $this->payment_status,
            'addons' => $this->whenLoaded('addons', fn (): array => $this->addons->map(fn ($addon): array => [
                'id' => $addon->id,
                'quantity' => (int) $addon->quantity,
                'amount' => (float) $addon->amount,
                'billing_cycle' => $addon->billing_cycle,
                'stripe_price_id' => $addon->stripe_price_id,
                'addon' => $addon->relationLoaded('addon') && $addon->addon ? [
                    'id' => $addon->addon->id,
                    'name' => $addon->addon->name,
                    'slug' => $addon->addon->slug,
                    'pricing_type' => $addon->addon->pricing_type,
                ] : null,
            ])->values()->all()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
