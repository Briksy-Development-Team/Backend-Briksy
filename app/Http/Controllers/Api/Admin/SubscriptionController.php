<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\SuperAdmin\SubscriptionPlanResource;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Webhooks\WebhookDispatcherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    public function __construct(private readonly WebhookDispatcherService $webhookDispatcher)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $organization = $user->organization()->with(['plan', 'currentSubscription'])->first();
        abort_unless($organization, 403, 'Subscription management is available to admin organizations only.');

        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderByDesc('popular')
            ->orderBy('ranking_priority')
            ->orderBy('price')
            ->get();

        return $this->success([
            'plans' => SubscriptionPlanResource::collection($plans)->resolve(),
            'subscription' => $user->subscriptionSummary(),
        ], 'Subscription plans retrieved successfully.');
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        return $this->success([
            'subscription' => $user->subscriptionSummary(),
        ], 'Subscription status retrieved successfully.');
    }

    public function select(Request $request, SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $organization = $user->organization;
        abort_unless($organization, 403, 'Subscription management is available to admin organizations only.');

        abort_unless($subscriptionPlan->is_active, 404, 'Subscription plan not found.');

        $subscription = DB::transaction(function () use ($organization, $subscriptionPlan, $user): Subscription {
            $organization->update([
                'plan_id' => $subscriptionPlan->id,
                'subscription_status' => 'active',
                'subscription_activated_at' => now(),
                'trial_ends_at' => $organization->trial_ends_at ?? now(),
            ]);

            return Subscription::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                ],
                [
                    'subscription_plan_id' => $subscriptionPlan->id,
                    'stripe_subscription_id' => 'manual-' . Str::uuid(),
                    'status' => 'active',
                    'currency' => 'AUD',
                    'amount' => (float) ($subscriptionPlan->monthly_price ?? $subscriptionPlan->price ?? 0),
                    'current_period_start' => now(),
                    'current_period_end' => now()->addMonth(),
                ]
            );
        });

        $organization->load(['plan', 'currentSubscription']);
        $user->setRelation('organization', $organization);

        $this->webhookDispatcher->dispatch(
            'subscription.updated',
            [
                'subscription_id' => $subscription->id,
                'plan_id' => $subscriptionPlan->id,
                'status' => $subscription->status,
                'billing_cycle' => $subscription->billing_cycle,
                'amount' => $subscription->amount,
            ],
            $organization,
            $user,
            sprintf('subscription.updated:%s', $subscription->id)
        );

        return $this->success([
            'plan' => new SubscriptionPlanResource($subscriptionPlan->fresh()),
            'subscription' => $user->subscriptionSummary(),
            'current_subscription' => [
                'id' => $subscription->id,
                'subscription_plan_id' => $subscription->subscription_plan_id,
                'status' => $subscription->status,
                'current_period_start' => $subscription->current_period_start?->toISOString(),
                'current_period_end' => $subscription->current_period_end?->toISOString(),
            ],
        ], 'Subscription plan selected successfully.');
    }
}
