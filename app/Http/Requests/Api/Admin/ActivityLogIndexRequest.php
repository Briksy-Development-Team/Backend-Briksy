<?php

namespace App\Http\Requests\Api\Admin;

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
        ];
    }

    public function allowedFilters(): array
    {
        return [
            'user_id' => 'user_id',
            'role' => 'user_role',
            'action' => 'action',
            'module' => 'module',
            'ip_address' => 'ip_address',
        ];
    }
}
