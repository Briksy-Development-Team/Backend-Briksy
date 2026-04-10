<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Http\Requests\Api\ApiListRequest;
use Illuminate\Validation\Rule;

class ServiceProviderIndexRequest extends ApiListRequest
{
    public function allowedSorts(): array
    {
        return [
            'created_at' => 'created_at',
            'name' => 'name',
            'email' => 'email',
            'provider_type' => 'provider_type',
        ];
    }

    protected function filterRules(): array
    {
        return [
            'filter.provider_type' => ['nullable', 'string', Rule::in(['organization', 'sole_trader'])],
            'filter.is_verified' => ['nullable', 'boolean'],
            'filter.type_id' => ['nullable', 'uuid', 'exists:organization_types,id'],
        ];
    }
}

