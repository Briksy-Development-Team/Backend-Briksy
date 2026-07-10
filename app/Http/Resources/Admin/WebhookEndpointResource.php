<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebhookEndpointResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'endpoint_url' => $this->endpoint_url,
            'secret_key' => $this->when(isset($this->secret_key), $this->secret_key),
            'description' => $this->description,
            'status' => $this->status,
            'health_status' => $this->health_status,
            'events' => collect($this->events ?? [])->values()->all(),
            'retry_count' => (int) $this->retry_count,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'created_by_user' => $this->whenLoaded('creator', fn (): ?array => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ] : null),
            'updated_by_user' => $this->whenLoaded('updater', fn (): ?array => $this->updater ? [
                'id' => $this->updater->id,
                'name' => $this->updater->name,
                'email' => $this->updater->email,
            ] : null),
            'last_delivery' => $this->whenLoaded('latestDelivery', fn (): ?array => $this->latestDelivery ? [
                'id' => $this->latestDelivery->id,
                'event' => $this->latestDelivery->event,
                'delivery_status' => $this->latestDelivery->delivery_status,
                'http_status' => $this->latestDelivery->http_status,
                'created_at' => $this->latestDelivery->created_at?->toISOString(),
            ] : null),
        ];
    }
}
