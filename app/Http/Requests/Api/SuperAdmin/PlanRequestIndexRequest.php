<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Http\Requests\Api\ApiListRequest;

class PlanRequestIndexRequest extends ApiListRequest
{
    public function allowedSorts(): array
    {
        return [
            'created_at' => 'created_at',
            'status' => 'status',
            'contact_name' => 'contact_name',
            'company_name' => 'company_name',
            'reviewed_at' => 'reviewed_at',
        ];
    }

    public function searchableColumns(): array
    {
        return [
            'company_name',
            'contact_name',
            'contact_email',
            'requested_plan_name',
            'billing_cycle',
            'status',
        ];
    }

    public function allowedFilters(): array
    {
        return [
            'status' => 'status',
            'organization_id' => 'organization_id',
            'plan_id' => 'plan_id',
        ];
    }

    protected function filterRules(): array
    {
        return [
            'filter.status' => ['nullable', 'string'],
            'filter.organization_id' => ['nullable', 'uuid'],
            'filter.plan_id' => ['nullable', 'uuid'],
        ];
    }
}
