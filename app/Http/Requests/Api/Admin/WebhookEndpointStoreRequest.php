<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ApiListRequest;
use Illuminate\Validation\Rule;

class WebhookEndpointStoreRequest extends ApiListRequest
{
    public function rules(): array
    {
        $eventKeys = collect(config('webhooks.registry', []))
            ->pluck('key')
            ->all();

        return [
            'name' => ['required', 'string', 'max:150'],
            'endpoint_url' => ['required', 'string', 'max:2048', 'url', 'starts_with:https://'],
            'secret_key' => ['nullable', 'string', 'min:32', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'disabled'])],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in($eventKeys)],
            'retry_count' => ['nullable', 'integer', 'min:0', 'max:10'],
        ];
    }

    public function allowedSorts(): array
    {
        return [
            'created_at' => 'created_at',
            'name' => 'name',
            'status' => 'status',
        ];
    }
}
