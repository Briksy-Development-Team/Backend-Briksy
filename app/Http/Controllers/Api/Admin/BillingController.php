<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Http\Controllers\Api\Concerns\AppliesOrganizationScope;
use App\Http\Requests\Api\Admin\BillingCheckoutRequest;
use App\Http\Resources\SuperAdmin\AddonResource;
use App\Http\Resources\SuperAdmin\SubscriptionPlanResource;
use App\Http\Resources\SuperAdmin\SubscriptionResource;
use App\Models\Addon;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\Price;
use Stripe\Product;
use Stripe\StripeClient;

class BillingController extends Controller
{
    use AppliesOrganizationScope;

    public function currentSubscription(Request $request): JsonResponse
    {
        $organization = $this->organization($request);

        return $this->success([
            'subscription' => $this->subscriptionPayload($organization->currentSubscription?->loadMissing(['organization', 'plan', 'addons.addon'])),
        ], 'Current subscription retrieved successfully.');
    }

    public function plans(Request $request): JsonResponse
    {
        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->with(['addons' => fn ($query) => $query->where('is_active', true)])
            ->orderByDesc('popular')
            ->orderBy('ranking_priority')
            ->get();

        return $this->success([
            'plans' => SubscriptionPlanResource::collection($plans)->resolve(),
        ], 'Billing plans retrieved successfully.');
    }

    public function addons(): JsonResponse
    {
        $addons = Addon::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return $this->success([
            'addons' => AddonResource::collection($addons)->resolve(),
        ], 'Available add-ons retrieved successfully.');
    }

    public function checkout(BillingCheckoutRequest $request): JsonResponse
    {
        $organization = $this->organization($request);
        $validated = $request->validated();
        $plan = SubscriptionPlan::query()->with('addons')->findOrFail($validated['plan_id']);

        if (!$plan->is_active) {
            return response()->json(['success' => false, 'message' => 'Selected plan is inactive.'], 422);
        }

        $billingCycle = $validated['billing_cycle'];
        $addonIds = collect($validated['addon_ids'] ?? [])->values()->all();
        $addons = Addon::query()->whereIn('id', $addonIds)->where('is_active', true)->get()->keyBy('id');
        $quantities = $validated['quantities'] ?? [];

        $currency = $plan->currency ?? config('services.stripe.currency', 'AUD');
        $planAmount = (float) ($billingCycle === 'yearly' ? ($plan->yearly_price ?? 0) : ($plan->monthly_price ?? 0));
        $addonAmount = 0.0;

        foreach ($addons as $addon) {
            $quantity = max(1, (int) ($quantities[$addon->id] ?? 1));
            $price = match ($billingCycle) {
                'yearly' => (float) ($addon->yearly_price ?? $addon->monthly_price ?? $addon->one_time_price ?? 0),
                default => (float) ($addon->monthly_price ?? $addon->one_time_price ?? 0),
            };

            $addonAmount += $price * $quantity;
        }

        $stripeKey = config('services.stripe.secret');
        if (!$stripeKey) {
            return response()->json(['success' => false, 'message' => 'Stripe secret is not configured.'], 422);
        }
        if (!class_exists(StripeClient::class)) {
            return response()->json(['success' => false, 'message' => 'Stripe PHP SDK is not installed. Run composer install to enable checkout.'], 500);
        }

        $stripe = new StripeClient($stripeKey);
        $customerId = $organization->stripe_customer_id;

        if (!$customerId) {
            $customer = $stripe->customers->create([
                'name' => $organization->name,
                'email' => $organization->contact_email,
                'metadata' => [
                    'organization_id' => $organization->id,
                ],
            ]);
            $customerId = $customer->id;
            $organization->update(['stripe_customer_id' => $customerId]);
        }

        $lineItems = [];
        $planPriceId = $billingCycle === 'yearly' ? $plan->stripe_yearly_price_id : $plan->stripe_monthly_price_id;

        if ($planPriceId) {
            $lineItems[] = ['price' => $planPriceId, 'quantity' => 1];
        } else {
            $lineItems[] = [
                'price_data' => [
                    'currency' => strtolower($currency),
                    'unit_amount' => (int) round($planAmount * 100),
                    'product_data' => ['name' => $plan->name],
                    'recurring' => ['interval' => $billingCycle],
                ],
                'quantity' => 1,
            ];
        }

        foreach ($addons as $addon) {
            $quantity = max(1, (int) ($quantities[$addon->id] ?? 1));
            $amount = match ($billingCycle) {
                'yearly' => (float) ($addon->yearly_price ?? $addon->monthly_price ?? $addon->one_time_price ?? 0),
                default => (float) ($addon->monthly_price ?? $addon->one_time_price ?? 0),
            };

            $addonItem = [
                'price_data' => [
                    'currency' => strtolower($currency),
                    'unit_amount' => (int) round($amount * 100),
                    'product_data' => ['name' => $addon->name],
                ],
                'quantity' => $quantity,
            ];

            if (in_array($addon->pricing_type, ['monthly', 'yearly'], true)) {
                $addonItem['price_data']['recurring'] = ['interval' => $billingCycle];
            }

            $lineItems[] = $addonItem;
        }

        $session = $stripe->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $customerId,
            'line_items' => $lineItems,
            'success_url' => config('services.stripe.success_url'),
            'cancel_url' => config('services.stripe.cancel_url'),
            'metadata' => [
                'organization_id' => $organization->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $billingCycle,
                'addon_ids' => json_encode($addonIds),
            ],
            'subscription_data' => [
                'metadata' => [
                    'organization_id' => $organization->id,
                    'plan_id' => $plan->id,
                    'billing_cycle' => $billingCycle,
                    'addon_ids' => json_encode($addonIds),
                ],
            ],
        ]);

        DB::transaction(function () use ($organization, $plan, $billingCycle, $session, $customerId, $planAmount, $addonAmount, $addons, $quantities, $currency): void {
            $subscription = Subscription::query()->updateOrCreate(
                ['organization_id' => $organization->id],
                [
                    'subscription_plan_id' => $plan->id,
                    'billing_cycle' => $billingCycle,
                    'currency' => $currency,
                    'amount' => $planAmount + $addonAmount,
                    'stripe_customer_id' => $customerId,
                    'stripe_checkout_session_id' => $session->id,
                    'status' => 'incomplete',
                    'payment_status' => 'pending',
                ]
            );

            $subscription->addons()->delete();

            foreach ($addons as $addon) {
                $quantity = max(1, (int) ($quantities[$addon->id] ?? 1));
                $amount = match ($billingCycle) {
                    'yearly' => (float) ($addon->yearly_price ?? $addon->monthly_price ?? $addon->one_time_price ?? 0),
                    default => (float) ($addon->monthly_price ?? $addon->one_time_price ?? 0),
                };

                SubscriptionAddon::query()->create([
                    'subscription_id' => $subscription->id,
                    'addon_id' => $addon->id,
                    'quantity' => $quantity,
                    'amount' => $amount * $quantity,
                    'billing_cycle' => $billingCycle,
                    'stripe_price_id' => null,
                ]);
            }
        });

        return $this->success([
            'checkout_session_id' => $session->id,
            'checkout_url' => $session->url,
        ], 'Checkout session created successfully.');
    }

    public function subscriptions(Request $request): JsonResponse
    {
        $organization = $this->organization($request);
        $subscriptions = Subscription::query()
            ->where('organization_id', $organization->id)
            ->with(['organization', 'plan', 'addons.addon'])
            ->latest()
            ->get();

        return $this->success([
            'subscriptions' => SubscriptionResource::collection($subscriptions)->resolve(),
        ], 'Subscription history retrieved successfully.');
    }

    private function organization(Request $request): Organization
    {
        $organization = $request->user()?->organization;
        abort_unless($organization, 403, 'Admin account is not assigned to an organization.');

        return $organization;
    }

    private function subscriptionPayload(?Subscription $subscription): ?array
    {
        if (!$subscription) {
            return null;
        }

        return SubscriptionResource::make($subscription)->resolve();
    }
}
