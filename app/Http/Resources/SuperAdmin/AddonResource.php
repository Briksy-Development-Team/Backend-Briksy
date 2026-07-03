<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'feature_key' => $this->feature_key,
            'pricing_type' => $this->pricing_type,
            'monthly_price' => $this->monthly_price !== null ? (float) $this->monthly_price : null,
            'yearly_price' => $this->yearly_price !== null ? (float) $this->yearly_price : null,
            'one_time_price' => $this->one_time_price !== null ? (float) $this->one_time_price : null,
            'currency' => $this->currency,
            'limits' => $this->limits,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
