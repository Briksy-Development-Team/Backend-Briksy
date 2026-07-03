<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_code' => $this->request_code,
            'organization_id' => $this->organization_id,
            'plan_id' => $this->plan_id,
            'company_name' => $this->company_name,
            'contact_name' => $this->contact_name,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'requested_plan_name' => $this->requested_plan_name,
            'billing_cycle' => $this->billing_cycle,
            'message' => $this->message,
            'status' => $this->status,
            'admin_notes' => $this->admin_notes,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'organization' => $this->whenLoaded('organization', fn (): array => [
                'id' => $this->organization?->id,
                'name' => $this->organization?->name,
            ]),
            'plan' => $this->whenLoaded('plan', fn (): array => [
                'id' => $this->plan?->id,
                'name' => $this->plan?->name,
                'price' => (int) ($this->plan?->price ?? 0),
            ]),
        ];
    }
}
