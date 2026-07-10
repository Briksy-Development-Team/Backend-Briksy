<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ApiListRequest;
use Illuminate\Validation\Rule;

class WebhookEndpointIndexRequest extends ApiListRequest
{
    public function allowedSorts(): array
    {
        return [
            'created_at' => 'created_at',
            'name' => 'name',
            'status' => 'status',
        ];
    }

    public function searchableColumns(): array
    {
        return ['name', 'endpoint_url', 'description', 'events'];
    }

    protected function filterRules(): array
    {
        return [
            'filter.status' => ['nullable', 'in:active,disabled'],
            'filter.event' => ['nullable', 'string', 'max:120', Rule::in(collect(config('webhooks.registry', []))->pluck('key')->all())],
            'filter.company_id' => ['nullable', 'uuid', 'exists:organizations,id'],
        ];
    }
}
