<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Http\Requests\Api\ApiListRequest;

class CouponIndexRequest extends ApiListRequest
{
    public function allowedSorts(): array
    {
        return [
            'created_at' => 'created_at',
            'expires_at' => 'expires_at',
            'status' => 'status',
            'code' => 'code',
        ];
    }

    public function searchableColumns(): array
    {
        return ['code', 'name', 'description', 'status'];
    }

    public function allowedFilters(): array
    {
        return [
            'status' => 'status',
            'discount_type' => 'discount_type',
        ];
    }

    protected function filterRules(): array
    {
        return [
            'filter.status' => ['nullable', 'string'],
            'filter.discount_type' => ['nullable', 'string'],
        ];
    }
}
