<?php

namespace App\Http\Controllers\Api\Seeker;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\SuperAdmin\SubscriptionPlanResource;
use App\Models\Addon;
use App\Models\SubscriptionPlan;

class SubscriptionPlanController extends Controller
{
    public function index()
    {
        $addons = Addon::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->with(['addons' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('plan_family')
            ->orderBy('ranking_priority')
            ->get();

        // Add-ons are optional checkout upgrades. If a plan has no explicit
        // catalogue mapping yet, expose the active global add-on catalogue so
        // the public checkout never loses the add-ons section.
        $plans->each(function (SubscriptionPlan $plan) use ($addons): void {
            if ($plan->addons->isEmpty()) {
                $plan->setRelation('addons', $addons);
            }
        });

        return $this->success(SubscriptionPlanResource::collection($plans), 'Public subscription plans retrieved successfully.');
    }
}
