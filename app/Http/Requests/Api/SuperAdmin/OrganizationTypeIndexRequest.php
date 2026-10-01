<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Http\Requests\Api\ApiListRequest;

class OrganizationTypeIndexRequest extends ApiListRequest
{
    public function allowedSorts(): array
    {
        return [
            'created_at' => 'created_at',
            'name' => 'name',
            'sort_order' => 'sort_order',
        ];
    }

    public function searchableColumns(): array
    {
        return [
            'name',
            'slug',
            'module',
            'display_name',
        ];
    }

    protected function filterRules(): array
    {
        return [
            'filter.module' => ['nullable', 'string', 'max:100'],
            'filter.is_active' => ['nullable', 'boolean'],
        ];
    }
}
