<?php

namespace App\Http\Requests\Api\Seeker;

use App\Http\Requests\Api\ApiIndexRequest;

class PropertyListingIndexRequest extends ApiIndexRequest
{
    public function allowedSorts(): array
    {
        return [
            'created_at' => 'created_at',
            'title' => 'title',
            'rating' => 'avg_prop_rating',
        ];
    }

    protected function filterRules(): array
    {
        return [
            'suburb' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:10'],
            'organization_slug' => ['nullable', 'string', 'max:100'],
            'organization_type' => ['nullable', 'string', 'max:100'],
            'service_slug' => ['nullable', 'string', 'max:100'],
            'verified_only' => ['nullable', 'boolean'],
        ];
    }
}
