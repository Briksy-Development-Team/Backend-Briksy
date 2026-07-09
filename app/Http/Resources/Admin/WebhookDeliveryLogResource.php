<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebhookDeliveryLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'webhook_endpoint_id' => $this->webhook_endpoint_id,
            'company_id' => $this->company_id,
            'event_id' => $this->event_id,
            'event' => $this->event,
            'endpoint_url' => $this->endpoint_url,
            'deduplication_key' => $this->deduplication_key,
            'payload' => $this->payload,
            'signature' => $this->signature,
            'response_body' => $this->response_body,
            'http_status' => $this->http_status,
            'response_time_ms' => $this->response_time_ms,
            'delivery_status' => $this->delivery_status,
            'attempt_count' => (int) $this->attempt_count,
            'retry_count' => (int) $this->retry_count,
            'error_message' => $this->error_message,
            'last_attempt_at' => $this->last_attempt_at?->toISOString(),
            'next_retry_at' => $this->next_retry_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'failed_at' => $this->failed_at?->toISOString(),
            'dead_lettered_at' => $this->dead_lettered_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'endpoint' => $this->whenLoaded('endpoint', fn (): ?array => $this->endpoint ? [
                'id' => $this->endpoint->id,
                'name' => $this->endpoint->name,
                'endpoint_url' => $this->endpoint->endpoint_url,
                'status' => $this->endpoint->status,
            ] : null),
        ];
    }
}
