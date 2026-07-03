<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Http\Requests\Api\ApiListRequest;

class AddonIndexRequest extends ApiListRequest
{
    public function allowedSorts(): array
    {
        return [
            'created_at' => 'created_at',
            'name' => 'name',
            'slug' => 'slug',
            'sort_order' => 'sort_order',
        ];
    }

    public function searchableColumns(): array
    {
        return ['name', 'slug', 'feature_key', 'pricing_type'];
    }

    public function allowedFilters(): array
    {
        return [
            'is_active' => 'is_active',
            'pricing_type' => 'pricing_type',
        ];
    }

    protected function filterRules(): array
    {
        return [
            'filter.is_active' => ['nullable', 'boolean'],
            'filter.pricing_type' => ['nullable', 'string'],
        ];
    }
}
