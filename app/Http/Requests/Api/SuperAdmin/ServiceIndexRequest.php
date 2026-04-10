<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Http\Requests\Api\ApiListRequest;

class ServiceIndexRequest extends ApiListRequest
{
    public function allowedSorts(): array
    {
        return [
            'created_at' => 'created_at',
            'name' => 'name',
            'is_active' => 'is_active',
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

    public function allowedFilters(): array
    {
        return [
            'type_id' => 'type_id',
            'service_group_id' => 'service_group_id',
            'is_active' => 'is_active',
        ];
    }

    protected function filterRules(): array
    {
        return [
            'filter.type_id' => ['nullable', 'uuid', 'exists:organization_types,id'],
            'filter.service_group_id' => ['nullable', 'uuid', 'exists:service_groups,id'],
            'filter.is_active' => ['nullable', 'boolean'],
        ];
    }
}

