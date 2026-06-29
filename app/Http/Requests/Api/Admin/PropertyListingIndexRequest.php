<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ApiIndexRequest;

class PropertyListingIndexRequest extends ApiIndexRequest
{
    public function allowedSorts(): array
    {
        return [
            'created_at' => 'created_at',
            'title' => 'title',
            'status' => 'status',
            'rating' => 'avg_prop_rating',
        ];
    }

    public function searchableColumns(): array
    {
        return [
            'title',
            'description',
            'suburb',
            'postcode',
        ];
    }

    protected function filterRules(): array
    {
        return [
            'filter.status' => ['nullable', 'string', 'max:50'],
            'filter.suburb' => ['nullable', 'string', 'max:100'],
            'filter.state' => ['nullable', 'string', 'max:50'],
            'filter.postcode' => ['nullable', 'string', 'max:10'],
            'filter.property_type_id' => ['nullable', 'uuid'],
            'filter.organization_id' => ['nullable', 'uuid', 'exists:organizations,id'],
            'filter.organization_slug' => ['nullable', 'string', 'max:100'],
            'filter.verified_only' => ['nullable', 'boolean'],
        ];
    }
}
