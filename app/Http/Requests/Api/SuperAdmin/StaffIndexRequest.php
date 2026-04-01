<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Http\Requests\Api\ApiListRequest;

class StaffIndexRequest extends ApiListRequest
{
    public function allowedSorts(): array
    {
        return [
            'created_at' => 'created_at',
            'name' => 'name',
            'email' => 'email',
        ];
    }

    public function searchableColumns(): array
    {
        return [
            'name',
            'email',
        ];
    }

    public function allowedFilters(): array
    {
        return [
            'organization_id' => 'organization_id',
        ];
    }

    protected function filterRules(): array
    {
        return [
            'filter.organization_id' => ['nullable', 'uuid', 'exists:organizations,id'],
        ];
    }
}