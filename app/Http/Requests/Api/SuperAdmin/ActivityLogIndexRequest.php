<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Http\Requests\Api\ActivityLogIndexRequest as BaseActivityLogIndexRequest;

class ActivityLogIndexRequest extends BaseActivityLogIndexRequest
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
            'organization_id' => 'organization_id',
        ];
    }

    public function allowedFilters(): array
    {
        return [
            'organization_id' => 'organization_id',
            'company_id' => 'organization_id',
            'user_id' => 'user_id',
            'role' => 'user_role',
            'action' => 'action',
            'module' => 'module',
            'ip_address' => 'ip_address',
        ];
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'filter.organization_id' => ['nullable', 'uuid', 'exists:organizations,id'],
            'filter.company_id' => ['nullable', 'uuid', 'exists:organizations,id'],
        ]);
    }
}
