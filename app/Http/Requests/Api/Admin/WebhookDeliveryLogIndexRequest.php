<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ApiListRequest;
use Illuminate\Validation\Rule;

class WebhookDeliveryLogIndexRequest extends ApiListRequest
{
    public function allowedSorts(): array
    {
        return [
            'created_at' => 'created_at',
            'event' => 'event',
            'delivery_status' => 'delivery_status',
            'http_status' => 'http_status',
            'response_time_ms' => 'response_time_ms',
        ];
    }

    public function searchableColumns(): array
    {
        return ['event', 'endpoint_url', 'delivery_status', 'error_message'];
    }

    protected function filterRules(): array
    {
        return [
            'filter.event' => ['nullable', 'string', 'max:120', Rule::in(collect(config('webhooks.registry', []))->pluck('key')->all())],
            'filter.status' => ['nullable', 'in:pending,retrying,delivered,failed,dead_letter'],
            'filter.endpoint_id' => ['nullable', 'uuid', 'exists:webhook_endpoints,id'],
            'filter.company_id' => ['nullable', 'uuid', 'exists:organizations,id'],
            'filter.date_from' => ['nullable', 'date'],
            'filter.date_to' => ['nullable', 'date'],
        ];
    }
}
