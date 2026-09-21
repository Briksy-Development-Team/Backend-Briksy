<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Http\Requests\Api\ApiListRequest;

class SeekerIndexRequest extends ApiListRequest
{
    public function allowedSorts(): array
    {
        return [
            'display_id' => 'generated_id',
            'created_at' => 'created_at',
            'name' => 'name',
            'display_name' => 'display_name',
            'email' => 'email',
            'mobile_number' => 'mobile_number',
            'email_verified_at' => 'email_verified_at',
            'mobile_verified_at' => 'mobile_verified_at',
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
