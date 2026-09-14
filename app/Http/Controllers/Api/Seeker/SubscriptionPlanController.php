<?php

namespace App\Http\Controllers\Api\Seeker;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\SuperAdmin\SubscriptionPlanResource;
use App\Models\SubscriptionPlan;

class SubscriptionPlanController extends Controller
{
    public function index()
    {
        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('plan_family')
            ->orderBy('ranking_priority')
            ->get();

        return $this->success(SubscriptionPlanResource::collection($plans), 'Public subscription plans retrieved successfully.');
    }
}
