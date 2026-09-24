<?php

namespace App\Http\Requests\Api\Admin;

use App\Http\Requests\Api\ApiIndexRequest;

class StaffIndexRequest extends ApiIndexRequest
{
    public function allowedSorts(): array
    {
        return [
            'display_id' => 'generated_id',
            'name' => 'name',
            'email' => 'email',
            'created_at' => 'created_at',
        ];
    }

    public function searchableColumns(): array
    {
        return ['name', 'email'];
    }

    protected function filterRules(): array
    {
        return [
            'filter.roles' => ['nullable', 'string', 'max:100'],
            'filter.created_at' => ['nullable', 'string', 'max:50'],
        ];
    }
}
