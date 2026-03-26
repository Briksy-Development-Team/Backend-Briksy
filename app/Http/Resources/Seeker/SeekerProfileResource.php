<?php

namespace App\Http\Resources\Seeker;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeekerProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'current_postcode' => $this->current_postcode,
            'preferred_budget_min' => $this->preferred_budget_min !== null ? (float) $this->preferred_budget_min : null,
            'preferred_budget_max' => $this->preferred_budget_max !== null ? (float) $this->preferred_budget_max : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
