<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Http\Requests\Api\ApiListRequest;

class OrganizationTypeIndexRequest extends ApiListRequest
{
    public function allowedSorts(): array
    {
        return [
            'created_at' => 'created_at',
            'name' => 'name',
        ];
    }

    public function searchableColumns(): array
    {
        return [
            'name',
            'slug',
        ];
    }
}