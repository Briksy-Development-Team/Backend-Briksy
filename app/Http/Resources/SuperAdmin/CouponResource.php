<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'display_id' => $this->display_id,
            'name' => $this->name,
            'description' => $this->description,
            'discount_type' => $this->discount_type,
            'discount_value' => (string) $this->discount_value,
            'max_discount_amount' => $this->max_discount_amount !== null ? (string) $this->max_discount_amount : null,
            'min_order_amount' => $this->min_order_amount !== null ? (string) $this->min_order_amount : null,
            'starts_at' => $this->starts_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'usage_limit' => $this->usage_limit,
            'usage_count' => $this->usage_count,
            'per_user_limit' => $this->per_user_limit,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
