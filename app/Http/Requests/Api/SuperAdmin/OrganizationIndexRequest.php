<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Http\Requests\Api\ApiListRequest;

class OrganizationIndexRequest extends ApiListRequest
{
    public function allowedSorts(): array
    {
        return [
            'created_at' => 'created_at',
            'name' => 'name',
            'ranking_priority' => 'ranking_priority',
            'avg_org_rating' => 'avg_org_rating',
        ];
    }

    public function searchableColumns(): array
    {
        return [
            'name',
            'trading_name',
            'slug',
            'abn',
            'acn',
            'business_type',
            'business_verification_status',
        ];
    }

    public function allowedFilters(): array
    {
        return [
            'type_id' => 'type_id',
            'business_type' => 'business_type',
            'business_verification_status' => 'business_verification_status',
            'is_verified' => 'is_verified',
        ];
    }

    protected function filterRules(): array
    {
        return [
            'filter.type_id' => ['nullable', 'uuid', 'exists:organization_types,id'],
            'filter.type_slug' => ['nullable', 'string', 'max:100'],
            'filter.service_slug' => ['nullable', 'string', 'max:100'],
            'filter.service_group_slug' => ['nullable', 'string', 'max:100'],
            'filter.business_type' => ['nullable', 'string', 'in:organisation,company,solo_trader'],
            'filter.business_verification_status' => ['nullable', 'string', 'in:pending,verified,rejected'],
            'filter.is_verified' => ['nullable', 'boolean'],
        ];
    }
}
