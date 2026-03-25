<?php

namespace App\Http\Resources\Seeker;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeekerSavedSearchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'budget_min' => $this->budget_min !== null ? (float) $this->budget_min : null,
            'budget_max' => $this->budget_max !== null ? (float) $this->budget_max : null,
            'location' => $this->location_json,
            'property_types' => $this->property_types_json,
            'filters' => $this->filters_json,
            'is_default' => (bool) $this->is_default,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
