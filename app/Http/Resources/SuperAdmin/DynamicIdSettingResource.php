<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DynamicIdSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entity_type' => $this->entity_type,
            'prefix' => $this->prefix,
            'separator' => $this->separator,
            'include_year' => (bool) $this->include_year,
            'include_month' => (bool) $this->include_month,
            'number_padding' => (int) $this->number_padding,
            'starting_number' => (int) $this->starting_number,
            'current_number' => (int) $this->current_number,
            'reset_frequency' => $this->reset_frequency,
            'last_reset_at' => $this->last_reset_at?->toISOString(),
            'is_active' => (bool) $this->is_active,
            'sample_preview' => app(\App\Services\DynamicIdGeneratorService::class)->preview($this->resource),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
