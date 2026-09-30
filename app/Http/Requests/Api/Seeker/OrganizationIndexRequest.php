<?php

namespace App\Http\Requests\Api\Seeker;

use App\Http\Requests\Api\ApiIndexRequest;

class OrganizationIndexRequest extends ApiIndexRequest
{
    public function allowedSorts(): array
    {
        return [
            'created_at' => 'created_at',
            'name' => 'name',
            'rating' => 'avg_org_rating',
            'priority' => 'ranking_priority',
        ];
    }

    protected function filterRules(): array
    {
        return [
            'type' => ['nullable', 'string', 'max:100'],
            'service_slug' => ['nullable', 'string', 'max:100'],
            'service_group_slug' => ['nullable', 'string', 'max:100'],
            'verified_only' => ['nullable', 'boolean'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'numeric', 'min:0', 'max:5000'],
        ];
    }
}
