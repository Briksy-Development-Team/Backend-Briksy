<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Http\Requests\Api\ApiListRequest;

class SeekerIndexRequest extends ApiListRequest
{
    public function allowedSorts(): array
    {
        return [
            'created_at' => 'created_at',
            'name' => 'name',
            'email' => 'email',
        ];
    }

    public function searchableColumns(): array
    {
        return [
            'name',
            'email',
            'mobile_number',
            'display_name',
        ];
    }

    protected function filterRules(): array
    {
        return [
            'filter.email_verified' => ['nullable', 'boolean'],
            'filter.mobile_verified' => ['nullable', 'boolean'],
        ];
    }
}