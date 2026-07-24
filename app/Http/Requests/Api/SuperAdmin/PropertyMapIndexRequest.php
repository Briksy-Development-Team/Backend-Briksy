<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Http\Requests\Api\ApiListRequest;

class PropertyMapIndexRequest extends ApiListRequest
{
    public function allowedSorts(): array
    {
        return [
            'created_at' => 'created_at',
            'title' => 'title',
            'status' => 'status',
        ];
    }

    public function searchableColumns(): array
    {
        return [
            'generated_id',
            'title',
            'suburb',
            'state',
            'country',
        ];
    }

    protected function filterRules(): array
    {
        return [
            'filter.status' => ['nullable', 'string', 'max:50'],
            'filter.organization' => ['nullable', 'string', 'max:255'],
            'filter.organization_id' => ['nullable', 'uuid', 'exists:organizations,id'],
            'filter.verified' => ['nullable', 'boolean'],
            'filter.country' => ['nullable', 'string', 'max:100'],
            'filter.state' => ['nullable', 'string', 'max:100'],
            'filter.city' => ['nullable', 'string', 'max:100'],
            'filter.property_type' => ['nullable', 'string', 'max:255'],
            'filter.property_type_id' => ['nullable', 'uuid', 'exists:property_types,id'],
        ];
    }
}
