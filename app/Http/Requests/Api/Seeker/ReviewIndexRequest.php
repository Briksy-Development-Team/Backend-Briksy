<?php

namespace App\Http\Requests\Api\Seeker;

use App\Http\Requests\Api\ApiIndexRequest;

class ReviewIndexRequest extends ApiIndexRequest
{
    public function allowedSorts(): array
    {
        return [
            'created_at' => 'created_at',
            'rating' => 'rating',
        ];
    }

    protected function filterRules(): array
    {
        return [
            'organization_id' => ['nullable', 'uuid', 'exists:organizations,id'],
            'property_listing_id' => ['nullable', 'uuid', 'exists:property_listings,id'],
        ];
    }
}
