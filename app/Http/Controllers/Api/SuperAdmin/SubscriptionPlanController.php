<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\SuperAdmin\SubscriptionPlanIndexRequest;
use App\Http\Requests\Api\SuperAdmin\SubscriptionPlanStoreRequest;
use App\Http\Requests\Api\SuperAdmin\SubscriptionPlanUpdateRequest;
use App\Http\Resources\SuperAdmin\SubscriptionPlanResource;
use App\Models\SubscriptionPlan;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionPlanController extends Controller
{
    public function index(SubscriptionPlanIndexRequest $request): JsonResponse
    {
        $query = SubscriptionPlan::query();

        ApiQueryBuilder::applySearch($query, $request->search(), $request->searchableColumns());
        ApiQueryBuilder::applyFilters($query, $request->filters(), $request->allowedFilters());
        ApiQueryBuilder::applySort($query, $request->sort(), $request->direction(), $request->allowedSorts(), 'created_at');

        $plans = $query->paginate($request->perPage())->withQueryString();

        return $this->paginated(
            SubscriptionPlanResource::collection($plans),
            $plans,
            'Subscription plans retrieved successfully.'
        );
    }

    public function store(SubscriptionPlanStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $features = collect($validated['features'] ?? [])
            ->map(fn (array $feature): array => [
                'name' => $feature['name'],
                'enabled' => (bool) ($feature['enabled'] ?? false),
                'value' => array_key_exists('value', $feature) && $feature['value'] !== null ? (int) $feature['value'] : null,
            ])
            ->values()
            ->all();

        $plan = SubscriptionPlan::query()->create([
            'name' => $validated['name'],
            'price' => $validated['price'],
            'property_limit' => $validated['propertyLimit'],
            'popular' => $validated['popular'],
            'features' => $features,
            'permissions' => collect($validated['permissions'] ?? [])->values()->all(),
            'is_active' => $validated['is_active'] ?? true,
            'stripe_price_id' => $validated['stripe_price_id'] ?? 'manual-' . str()->uuid(),
            'staff_seat_limit' => $validated['staff_seat_limit'] ?? 0,
            'has_visitor_analytics' => $validated['has_visitor_analytics'] ?? false,
            'ranking_priority' => $validated['ranking_priority'] ?? 1,
        ]);

        return $this->created(
            new SubscriptionPlanResource($plan),
            'Subscription plan created successfully.'
        );
    }

    public function show(SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        return $this->success(
            new SubscriptionPlanResource($subscriptionPlan),
            'Subscription plan retrieved successfully.'
        );
    }

    public function update(SubscriptionPlanUpdateRequest $request, SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        $validated = $request->validated();

        if (array_key_exists('features', $validated)) {
            $validated['features'] = collect($validated['features'] ?? [])
                ->map(fn (array $feature): array => [
                    'name' => $feature['name'],
                    'enabled' => (bool) ($feature['enabled'] ?? false),
                    'value' => array_key_exists('value', $feature) && $feature['value'] !== null ? (int) $feature['value'] : null,
                ])
                ->values()
                ->all();
        }

        if (array_key_exists('propertyLimit', $validated)) {
            $validated['property_limit'] = $validated['propertyLimit'];
            unset($validated['propertyLimit']);
        }

        if (array_key_exists('features', $validated)) {
            $validated['features'] = $validated['features'];
        }

        $subscriptionPlan->fill([
            'name' => $validated['name'] ?? $subscriptionPlan->name,
            'price' => $validated['price'] ?? $subscriptionPlan->price,
            'property_limit' => $validated['property_limit'] ?? $subscriptionPlan->property_limit,
            'popular' => $validated['popular'] ?? $subscriptionPlan->popular,
            'features' => $validated['features'] ?? $subscriptionPlan->features,
            'permissions' => $validated['permissions'] ?? $subscriptionPlan->permissions,
            'is_active' => $validated['is_active'] ?? $subscriptionPlan->is_active,
        ]);
        $subscriptionPlan->save();

        return $this->success(
            new SubscriptionPlanResource($subscriptionPlan),
            'Subscription plan updated successfully.'
        );
    }

    public function destroy(SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        $subscriptionPlan->update(['is_active' => false]);
        $subscriptionPlan->delete();

        return $this->success([], 'Subscription plan deleted successfully.');
    }

    public function toggle(Request $request, SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $subscriptionPlan->update([
            'is_active' => $validated['is_active'],
        ]);

        return $this->success(
            new SubscriptionPlanResource($subscriptionPlan->fresh()),
            'Subscription plan status updated successfully.'
        );
    }
}
