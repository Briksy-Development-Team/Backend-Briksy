<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Http\Requests\Api\ApiListRequest;

class EmailTemplateIndexRequest extends ApiListRequest
{
    public function allowedSorts(): array
    {
        return [
            'created_at' => 'created_at',
            'name' => 'name',
            'key' => 'key',
            'status' => 'status',
        ];
    }

    public function searchableColumns(): array
    {
        return ['key', 'name', 'subject', 'status'];
    }

    public function allowedFilters(): array
    {
        return [
            'status' => 'status',
        ];
    }

    protected function filterRules(): array
    {
        return [
            'filter.status' => ['nullable', 'string'],
        ];
    }
}
