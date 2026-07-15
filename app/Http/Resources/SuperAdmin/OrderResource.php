<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'reference_no' => $this->reference_no,
            'display_id' => $this->display_id,
            'display_number' => $this->display_number,
            'organization_id' => $this->organization_id,
            'user_id' => $this->user_id,
            'plan_id' => $this->plan_id,
            'coupon_id' => $this->coupon_id,
            'subtotal' => (string) $this->subtotal,
            'discount_amount' => (string) $this->discount_amount,
            'tax_amount' => (string) $this->tax_amount,
            'total_amount' => (string) $this->total_amount,
            'currency' => $this->currency,
            'billing_cycle' => $this->billing_cycle,
            'payment_status' => $this->payment_status,
            'order_status' => $this->order_status,
            'payment_method' => $this->payment_method,
            'transaction_reference' => $this->transaction_reference,
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'organization' => $this->whenLoaded('organization', fn (): array => [
                'id' => $this->organization?->id,
                'name' => $this->organization?->name,
            ]),
            'user' => $this->whenLoaded('user', fn (): array => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ]),
            'plan' => $this->whenLoaded('plan', fn (): array => [
                'id' => $this->plan?->id,
                'name' => $this->plan?->name,
            ]),
            'coupon' => $this->whenLoaded('coupon', fn (): array => [
                'id' => $this->coupon?->id,
                'code' => $this->coupon?->code,
                'discount_type' => $this->coupon?->discount_type,
            ]),
        ];
    }
}
