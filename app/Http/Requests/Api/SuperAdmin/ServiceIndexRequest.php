<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Http\Requests\Api\ApiIndexRequest;

class ServiceIndexRequest extends ApiIndexRequest
{
    public function allowedSorts(): array
    {
        return [
            'created_at' => 'created_at',
            'name' => 'name',
            'slug' => 'slug',
            'organization_count' => 'organizations_count',
            'service_group_count' => 'service_groups_count',
        ];
    }

    public function searchableColumns(): array
    {
        return [
            'name',
            'slug',
            'description',
        ];
    }

    protected function filterRules(): array
    {
        return [
            'filter.type_slug' => ['nullable', 'string', 'max:100'],
            'filter.organization_id' => ['nullable', 'uuid', 'exists:organizations,id'],
            'filter.is_active' => ['nullable', 'boolean'],
        ];
    }
}
