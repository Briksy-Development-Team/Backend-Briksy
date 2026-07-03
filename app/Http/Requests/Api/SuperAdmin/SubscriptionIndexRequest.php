<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Http\Requests\Api\ApiListRequest;

class SubscriptionIndexRequest extends ApiListRequest
{
    public function allowedSorts(): array
    {
        return [
            'created_at' => 'created_at',
            'current_period_end' => 'current_period_end',
            'amount' => 'amount',
            'status' => 'status',
        ];
    }

    public function searchableColumns(): array
    {
        return ['stripe_customer_id', 'stripe_subscription_id', 'stripe_checkout_session_id'];
    }

    public function allowedFilters(): array
    {
        return [
            'organization_id' => 'organization_id',
            'plan_id' => 'subscription_plan_id',
            'status' => 'status',
            'billing_cycle' => 'billing_cycle',
        ];
    }

    protected function filterRules(): array
    {
        return [
            'filter.organization_id' => ['nullable', 'uuid'],
            'filter.plan_id' => ['nullable', 'uuid'],
            'filter.status' => ['nullable', 'string'],
            'filter.billing_cycle' => ['nullable', 'string'],
        ];
    }
}
