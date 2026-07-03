<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmailTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'slug' => $this->slug ?? $this->key,
            'name' => $this->name,
            'subject' => $this->subject,
            'body' => $this->body,
            'variables' => $this->variables ?? [],
            'status' => $this->status,
            'is_active' => $this->is_active ?? ($this->status === 'active'),
            'module' => $this->module,
            'event_key' => $this->event_key,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
