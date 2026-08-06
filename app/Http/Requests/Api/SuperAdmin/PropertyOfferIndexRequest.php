<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Http\Requests\Api\ApiListRequest;

class PropertyOfferIndexRequest extends ApiListRequest
{
    public function allowedSorts(): array
    {
        return [
            'created_at' => 'created_at',
            'title' => 'title',
            'sort_order' => 'sort_order',
            'starts_at' => 'starts_at',
            'ends_at' => 'ends_at',
        ];
    }

    public function searchableColumns(): array
    {
        return ['title', 'summary', 'description', 'tag_label'];
    }

    public function allowedFilters(): array
    {
        return [
            'is_active' => 'is_active',
            'property_listing_id' => 'property_listing_id',
            'organization_id' => 'organization_id',
        ];
    }

    protected function filterRules(): array
    {
        return [
            'filter.is_active' => ['nullable', 'boolean'],
            'filter.property_listing_id' => ['nullable', 'uuid'],
            'filter.organization_id' => ['nullable', 'uuid'],
        ];
    }
}
