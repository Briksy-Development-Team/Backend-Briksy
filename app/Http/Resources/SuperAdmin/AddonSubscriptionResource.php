<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddonSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'addon_id' => $this->addon_id,
            'quantity' => (int) $this->quantity,
            'amount' => (float) $this->amount,
            'billing_cycle' => $this->billing_cycle,
            'stripe_price_id' => $this->stripe_price_id,
            'addon' => $this->whenLoaded('addon', fn (): ?array => $this->addon ? [
                'id' => $this->addon->id,
                'name' => $this->addon->name,
                'slug' => $this->addon->slug,
                'pricing_type' => $this->addon->pricing_type,
            ] : null),
        ];
    }
}
