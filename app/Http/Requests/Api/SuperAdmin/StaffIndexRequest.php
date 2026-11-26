<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Http\Requests\Api\ApiListRequest;

class StaffIndexRequest extends ApiListRequest
{
    public function allowedSorts(): array
    {
        return [
            'created_at' => 'created_at',
            'display_id' => 'generated_id',
            'display_name' => 'display_name',
            'email_verified_at' => 'email_verified_at',
            'mobile_verified_at' => 'mobile_verified_at',
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
            'filter.roles' => ['nullable', 'string', 'max:255'],
            'filter.created_at' => ['nullable', 'string', 'max:25'],
            'filter.organization_id' => ['nullable', 'uuid', 'exists:organizations,id'],
        ];
    }
}