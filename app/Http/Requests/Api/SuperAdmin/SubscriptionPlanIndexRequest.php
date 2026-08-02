<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Http\Requests\Api\ApiListRequest;

class SubscriptionPlanIndexRequest extends ApiListRequest
{
    public function allowedSorts(): array
    {
        return [
            'created_at' => 'created_at',
            'name' => 'name',
            'price' => 'price',
            'property_limit' => 'property_limit',
        ];
    }

    public function searchableColumns(): array
    {
        return [
            'name',
        ];
    }

    public function allowedFilters(): array
    {
        return [
            'is_active' => 'is_active',
            'popular' => 'popular',
            'plan_family' => 'plan_family',
        ];
    }

    protected function filterRules(): array
    {
        return [
            'filter.is_active' => ['nullable', 'boolean'],
            'filter.popular' => ['nullable', 'boolean'],
            'filter.plan_family' => ['nullable', 'string', 'in:property_owner,trades_professional'],
        ];
    }
}
