<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Http\Requests\Api\ApiListRequest;

class ServiceGroupIndexRequest extends ApiListRequest
{
    public function allowedSorts(): array
    {
        return [
            'created_at' => 'created_at',
            'name' => 'name',
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
        ];
    }

    protected function filterRules(): array
    {
        return [
            'filter.type_id' => ['nullable', 'uuid', 'exists:organization_types,id'],
        ];
    }
}

