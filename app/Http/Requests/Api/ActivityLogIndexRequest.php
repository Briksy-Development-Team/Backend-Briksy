<?php

namespace App\Http\Requests\Api;

abstract class ActivityLogIndexRequest extends ApiIndexRequest
{
    public function searchableColumns(): array
    {
        return [
            'user_name',
            'user_email',
            'action',
            'module',
            'description',
        ];
    }

    protected function filterRules(): array
    {
        return [
            'filter.user_id' => ['nullable', 'uuid'],
            'filter.user' => ['nullable', 'string', 'max:150'],
            'filter.role' => ['nullable', 'string', 'max:100'],
            'filter.action' => ['nullable', 'string', 'max:100'],
            'filter.module' => ['nullable', 'string', 'max:100'],
            'filter.ip_address' => ['nullable', 'string', 'max:45'],
            'filter.date_from' => ['nullable', 'date'],
            'filter.date_to' => ['nullable', 'date'],
        ];
    }
}
