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
        $query = SubscriptionPlan::query()->with('addons');

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
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'] ?? 0,
            'monthly_price' => $validated['monthly_price'] ?? null,
            'yearly_price' => $validated['yearly_price'] ?? null,
            'currency' => $validated['currency'] ?? 'AUD',
            'billing_enabled' => $validated['billing_enabled'] ?? true,
            'trial_days' => $validated['trial_days'] ?? null,
            'property_limit' => $validated['propertyLimit'],
            'popular' => $validated['popular'],
            'features' => $features,
            'permissions' => collect($validated['permissions'] ?? [])->values()->all(),
            'is_active' => $validated['is_active'] ?? true,
            'stripe_price_id' => 'internal-' . str()->uuid(),
            'staff_seat_limit' => $validated['staff_seat_limit'] ?? 0,
            'has_visitor_analytics' => $validated['has_visitor_analytics'] ?? false,
            'ranking_priority' => $validated['ranking_priority'] ?? 1,
        ]);

        return $this->created(
            new SubscriptionPlanResource($plan->fresh()->load('addons')),
            'Subscription plan created successfully.'
        );
    }

    public function show(SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        return $this->success(
            new SubscriptionPlanResource($subscriptionPlan->loadMissing('addons')),
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

        if (array_key_exists('monthly_price', $validated)) {
            $validated['monthly_price'] = $validated['monthly_price'];
        }

        if (array_key_exists('yearly_price', $validated)) {
            $validated['yearly_price'] = $validated['yearly_price'];
        }

        if (array_key_exists('features', $validated)) {
            $validated['features'] = $validated['features'];
        }

        $subscriptionPlan->fill([
            'name' => $validated['name'] ?? $subscriptionPlan->name,
            'description' => $validated['description'] ?? $subscriptionPlan->description,
            'price' => $validated['price'] ?? $subscriptionPlan->price,
            'monthly_price' => $validated['monthly_price'] ?? $subscriptionPlan->monthly_price,
            'yearly_price' => $validated['yearly_price'] ?? $subscriptionPlan->yearly_price,
            'currency' => $validated['currency'] ?? $subscriptionPlan->currency ?? 'AUD',
            'billing_enabled' => $validated['billing_enabled'] ?? $subscriptionPlan->billing_enabled ?? true,
            'trial_days' => $validated['trial_days'] ?? $subscriptionPlan->trial_days,
            'property_limit' => $validated['property_limit'] ?? $subscriptionPlan->property_limit,
            'popular' => $validated['popular'] ?? $subscriptionPlan->popular,
            'features' => $validated['features'] ?? $subscriptionPlan->features,
            'permissions' => $validated['permissions'] ?? $subscriptionPlan->permissions,
            'is_active' => $validated['is_active'] ?? $subscriptionPlan->is_active,
        ]);
        $subscriptionPlan->save();

        return $this->success(
            new SubscriptionPlanResource($subscriptionPlan->fresh()->load('addons')),
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
            new SubscriptionPlanResource($subscriptionPlan->fresh()->load('addons')),
            'Subscription plan status updated successfully.'
        );
    }

    public function attachAddon(Request $request, SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        $validated = $request->validate([
            'addon_id' => ['required', 'uuid', 'exists:addons,id'],
            'included_quantity' => ['nullable', 'integer', 'min:1'],
            'is_included' => ['sometimes', 'boolean'],
        ]);

        $subscriptionPlan->addons()->syncWithoutDetaching([
            $validated['addon_id'] => [
                'included_quantity' => $validated['included_quantity'] ?? null,
                'is_included' => $validated['is_included'] ?? true,
            ],
        ]);

        return $this->success(
            new SubscriptionPlanResource($subscriptionPlan->fresh()->load('addons')),
            'Add-on attached to plan successfully.'
        );
    }

    public function detachAddon(SubscriptionPlan $subscriptionPlan, string $addon): JsonResponse
    {
        $subscriptionPlan->addons()->detach($addon);

        return $this->success(
            new SubscriptionPlanResource($subscriptionPlan->fresh()->load('addons')),
            'Add-on detached from plan successfully.'
        );
    }
}
