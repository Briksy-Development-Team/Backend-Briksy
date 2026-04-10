<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Http\Requests\Api\ApiListRequest;
use Illuminate\Validation\Rule;

class PropertyIndexRequest extends ApiListRequest
{
    public function allowedSorts(): array
    {
        return [
            'created_at' => 'created_at',
            'title' => 'title',
            'status' => 'status',
            'avg_prop_rating' => 'avg_prop_rating',
        ];
    }

    public function searchableColumns(): array
    {
        return [
            'title',
            'suburb',
            'postcode',
        ];
    }

    public function allowedFilters(): array
    {
        return [
            'org_id' => 'org_id',
            'status' => 'status',
            'property_type_id' => 'property_type_id',
        ];
    }

    protected function filterRules(): array
    {
        return [
            'filter.org_id' => ['nullable', 'uuid', 'exists:organizations,id'],
            'filter.status' => ['nullable', 'string', Rule::in(['Draft', 'Published', 'Archived'])],
            'filter.property_type_id' => ['nullable', 'uuid', 'exists:property_types,id'],
        ];
    }
}

