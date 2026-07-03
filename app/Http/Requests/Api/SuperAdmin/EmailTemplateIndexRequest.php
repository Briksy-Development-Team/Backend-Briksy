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
            'slug' => 'slug',
            'status' => 'status',
            'module' => 'module',
            'event_key' => 'event_key',
        ];
    }

    public function searchableColumns(): array
    {
        return ['key', 'slug', 'name', 'subject', 'module', 'event_key', 'status'];
    }

    public function allowedFilters(): array
    {
        return [
            'status' => 'status',
            'module' => 'module',
            'event_key' => 'event_key',
            'is_active' => 'is_active',
        ];
    }

    protected function filterRules(): array
    {
        return [
            'filter.status' => ['nullable', 'string'],
            'filter.module' => ['nullable', 'string', 'max:255'],
            'filter.event_key' => ['nullable', 'string', 'max:255'],
            'filter.is_active' => ['nullable', 'boolean'],
        ];
    }
}
