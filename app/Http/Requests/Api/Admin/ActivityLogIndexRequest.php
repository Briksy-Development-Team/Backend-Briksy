<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ApiListRequest;

class ActivityLogIndexRequest extends ApiListRequest
{
    public function allowedSorts(): array
    {
        return [
            'created_at' => 'created_at',
            'user_name' => 'user_name',
            'user_email' => 'user_email',
            'action' => 'action',
            'module' => 'module',
            'ip_address' => 'ip_address',
        ];
    }

    public function searchableColumns(): array
    {
        return ['user_name', 'user_email', 'action', 'module', 'description', 'route', 'ip_address'];
    }

    public function allowedFilters(): array
    {
        return [
            'action' => 'action',
            'module' => 'module',
            'ip_address' => 'ip_address',
        ];
    }

    protected function filterRules(): array
    {
        return [
            'filter.date_from' => ['nullable', 'date'],
            'filter.date_to' => ['nullable', 'date'],
            'filter.user' => ['nullable', 'string', 'max:255'],
            'filter.action' => ['nullable', 'string', 'max:255'],
            'filter.module' => ['nullable', 'string', 'max:255'],
            'filter.ip_address' => ['nullable', 'string', 'max:255'],
            'filter.device' => ['nullable', 'string', 'max:255'],
        ];
    }
}
