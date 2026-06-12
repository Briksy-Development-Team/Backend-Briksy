<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'module' => $this->module,
            'action' => $this->action,
            'description' => $this->description,
            'guard_name' => $this->guard_name,
            'is_system' => (bool) $this->is_system,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
