<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Http\Requests\Api\ApiListRequest;

class OrderIndexRequest extends ApiListRequest
{
    public function allowedSorts(): array
    {
        return [
            'created_at' => 'created_at',
            'order_number' => 'order_number',
            'total_amount' => 'total_amount',
            'payment_status' => 'payment_status',
            'order_status' => 'order_status',
        ];
    }

    public function searchableColumns(): array
    {
        return ['order_number', 'transaction_reference', 'billing_cycle', 'payment_method', 'payment_status', 'order_status'];
    }

    public function allowedFilters(): array
    {
        return [
            'organization_id' => 'organization_id',
            'plan_id' => 'plan_id',
            'payment_status' => 'payment_status',
            'order_status' => 'order_status',
            'coupon_id' => 'coupon_id',
        ];
    }

    protected function filterRules(): array
    {
        return [
            'filter.organization_id' => ['nullable', 'uuid'],
            'filter.plan_id' => ['nullable', 'uuid'],
            'filter.coupon_id' => ['nullable', 'uuid'],
            'filter.payment_status' => ['nullable', 'string'],
            'filter.order_status' => ['nullable', 'string'],
        ];
    }
}
