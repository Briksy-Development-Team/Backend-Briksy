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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\Price;
use Stripe\Product;
use Stripe\StripeClient;
use App\Services\Webhooks\WebhookDispatcherService;

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
        $plan = SubscriptionPlan::query()->with(['addons' => fn ($query) => $query->where('is_active', true)])->findOrFail($validated['plan_id']);

        if (!$plan->is_active) {
            return response()->json(['success' => false, 'message' => 'Selected plan is inactive.'], 422);
        }
        if ($plan->billing_enabled === false) {
            return response()->json(['success' => false, 'message' => 'Selected plan does not support billing checkout.'], 422);
        }

        $billingCycle = $this->normalizeBillingCycle($validated['billing_cycle']);
        $selectedAddons = $this->normalizeSelectedAddons($validated);
        $addonIds = $selectedAddons->pluck('addon_id')->unique()->values()->all();
        $addons = Addon::query()->whereIn('id', $addonIds)->where('is_active', true)->get()->keyBy('id');
        if ($addons->count() !== count($addonIds)) {
            return response()->json(['success' => false, 'message' => 'One or more selected add-ons are invalid or inactive.'], 422);
        }

        $currency = $plan->currency ?? config('services.stripe.currency', 'AUD');
        $planAmount = $this->planAmount($plan, $billingCycle);
        $addonAmount = 0.0;
        $lineItems = [];

        $lineItems[] = [
            'price_data' => [
                'currency' => strtolower($currency),
                'unit_amount' => (int) round($planAmount * 100),
                'product_data' => ['name' => $plan->name],
                'recurring' => ['interval' => $billingCycle === 'yearly' ? 'year' : 'month'],
            ],
            'quantity' => 1,
        ];

        foreach ($selectedAddons as $selection) {
            $addon = $addons->get($selection['addon_id']);
            if (!$addon) {
                continue;
            }

            $quantity = max(1, (int) ($selection['quantity'] ?? 1));
            $price = $this->addonAmount($addon, $billingCycle);
            $addonAmount += $price * $quantity;

            $addonItem = [
                'price_data' => [
                    'currency' => strtolower($currency),
                    'unit_amount' => (int) round($price * 100),
                    'product_data' => ['name' => $addon->name],
                ],
                'quantity' => $quantity,
            ];

            if (in_array($addon->pricing_type, ['monthly', 'yearly'], true)) {
                $addonItem['price_data']['recurring'] = ['interval' => $billingCycle === 'yearly' ? 'year' : 'month'];
            }

            $lineItems[] = $addonItem;
        }

        $amount = $planAmount + $addonAmount;

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

        $session = $stripe->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $customerId,
            'line_items' => $lineItems,
            'success_url' => config('services.stripe.success_url'),
            'cancel_url' => config('services.stripe.cancel_url'),
            'metadata' => [
                'company_id' => $organization->id,
                'organization_id' => $organization->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $billingCycle,
                'addons' => $selectedAddons->toJson(),
                'selected_addons' => $selectedAddons->toJson(),
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => strtoupper($currency),
            ],
            'subscription_data' => [
                'metadata' => [
                    'company_id' => $organization->id,
                    'organization_id' => $organization->id,
                    'plan_id' => $plan->id,
                    'billing_cycle' => $billingCycle,
                    'addons' => $selectedAddons->toJson(),
                    'selected_addons' => $selectedAddons->toJson(),
                    'amount' => number_format($amount, 2, '.', ''),
                    'currency' => strtoupper($currency),
                ],
            ],
        ]);

        DB::transaction(function () use ($organization, $plan, $billingCycle, $session, $customerId, $planAmount, $addonAmount, $addons, $selectedAddons, $currency, $amount): void {
            $subscription = Subscription::query()->updateOrCreate(
                ['organization_id' => $organization->id],
                [
                    'subscription_plan_id' => $plan->id,
                    'billing_cycle' => $billingCycle,
                    'currency' => $currency,
                    'amount' => $amount,
                    'stripe_customer_id' => $customerId,
                    'stripe_checkout_session_id' => $session->id,
                    'status' => 'incomplete',
                    'payment_status' => 'pending',
                ]
            );

            $subscription->addons()->delete();

            foreach ($selectedAddons as $selection) {
                $addon = $addons->get($selection['addon_id']);
                if (!$addon) {
                    continue;
                }

                $quantity = max(1, (int) ($selection['quantity'] ?? 1));
                $addonAmountForSelection = $this->addonAmount($addon, $billingCycle);

                SubscriptionAddon::query()->create([
                    'subscription_id' => $subscription->id,
                    'addon_id' => $addon->id,
                    'quantity' => $quantity,
                    'amount' => $addonAmountForSelection * $quantity,
                    'billing_cycle' => $billingCycle,
                    'stripe_price_id' => null,
                ]);
            }
        });

        app(WebhookDispatcherService::class)->dispatch(
            'subscription.created',
            [
                'subscription_id' => $session->subscription ?? null,
                'checkout_session_id' => $session->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $billingCycle,
                'amount' => $amount,
                'status' => 'pending',
            ],
            $organization,
            $request->user(),
            $session->id
        );

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

    public function verifyCheckoutSession(Request $request, string $checkoutSessionId): JsonResponse
    {
        $organization = $this->organization($request);
        $stripeKey = config('services.stripe.secret');

        if (!$stripeKey) {
            return response()->json(['success' => false, 'message' => 'Stripe secret is not configured.'], 422);
        }
        if (!class_exists(StripeClient::class)) {
            return response()->json(['success' => false, 'message' => 'Stripe PHP SDK is not installed.'], 500);
        }

        $stripe = new StripeClient($stripeKey);
        Log::info('Stripe checkout verification started.', [
            'organization_id' => $organization->id,
            'checkout_session_id' => $checkoutSessionId,
            'user_id' => $request->user()?->id,
        ]);
        $checkoutSession = $stripe->checkout->sessions->retrieve($checkoutSessionId, [
            'expand' => ['subscription'],
        ]);

        $subscription = null;
        $stripeSubscription = null;
        $latestInvoice = null;
        $paymentIntent = null;
        $paymentMethodLabel = null;

        if ($checkoutSession->customer) {
            $subscription = Subscription::query()
                ->where('organization_id', $organization->id)
                ->where('stripe_checkout_session_id', $checkoutSessionId)
                ->first();
        }

        if ($checkoutSession->status === 'complete' && $checkoutSession->subscription) {
            $stripeSubscriptionId = is_object($checkoutSession->subscription)
                ? ($checkoutSession->subscription->id ?? null)
                : $checkoutSession->subscription;
            $stripeSubscription = $stripeSubscriptionId
                ? $stripe->subscriptions->retrieve($stripeSubscriptionId, [
                    'expand' => ['latest_invoice.payment_intent.payment_method'],
                ])
                : null;

            $planId = data_get($checkoutSession->metadata, 'plan_id');
            $billingCycle = $this->normalizeBillingCycle((string) data_get($checkoutSession->metadata, 'billing_cycle', 'monthly'));
            $amount = data_get($checkoutSession->metadata, 'amount');
            $amount = $amount !== null ? (float) $amount : ((float) ($stripeSubscription?->items->data[0]->price->unit_amount ?? 0)) / 100;
            $currency = strtoupper((string) data_get($checkoutSession->metadata, 'currency', $stripeSubscription?->currency ?? config('services.stripe.currency', 'AUD')));
            $plan = $planId ? SubscriptionPlan::query()->find($planId) : null;
            $latestInvoice = $stripeSubscription?->latest_invoice ?? null;
            $paymentIntent = is_object($latestInvoice) ? ($latestInvoice->payment_intent ?? null) : null;
            $paymentMethod = is_object($paymentIntent) ? ($paymentIntent->payment_method ?? null) : null;
            if (is_object($paymentMethod)) {
                $paymentMethodLabel = collect([
                    strtoupper((string) ($paymentMethod->brand ?? $paymentMethod->type ?? '')),
                    $paymentMethod->last4 ?? null,
                ])->filter()->join(' • ');
            }

            $subscription = Subscription::query()->updateOrCreate(
                ['organization_id' => $organization->id],
                [
                    'subscription_plan_id' => $plan?->id ?? $subscription?->subscription_plan_id,
                    'billing_cycle' => $billingCycle,
                    'currency' => $currency,
                    'amount' => $amount,
                    'stripe_customer_id' => $checkoutSession->customer,
                    'stripe_subscription_id' => $stripeSubscription?->id ?? null,
                    'stripe_checkout_session_id' => $checkoutSessionId,
                    'latest_invoice_id' => is_object($latestInvoice)
                        ? ($latestInvoice->id ?? null)
                        : ($latestInvoice ?? $checkoutSession->invoice ?? null),
                    'status' => $stripeSubscription?->status ?? 'active',
                    'payment_status' => $checkoutSession->payment_status ?? 'paid',
                    'current_period_start' => isset($stripeSubscription?->current_period_start) ? Carbon::createFromTimestamp($stripeSubscription->current_period_start) : null,
                    'current_period_end' => isset($stripeSubscription?->current_period_end) ? Carbon::createFromTimestamp($stripeSubscription->current_period_end) : null,
                ]
            );

            if ($plan && in_array($subscription->status, ['active', 'trialing'], true)) {
                $organization->update([
                    'plan_id' => $plan->id,
                    'subscription_status' => $subscription->status,
                    'subscription_activated_at' => now(),
                ]);
            }
        }

        $organization->load(['plan', 'currentSubscription']);
        $request->user()?->setRelation('organization', $organization);

        return $this->success([
                'checkout_session_id' => $checkoutSessionId,
                'checkout_status' => $checkoutSession->status,
                'payment_status' => $checkoutSession->payment_status ?? null,
                'subscription' => $this->subscriptionPayload($organization->currentSubscription?->loadMissing(['organization', 'plan', 'addons.addon'])),
                'stripe_details' => [
                    'subscription_id' => $subscription?->stripe_subscription_id ?? ($stripeSubscription?->id ?? null),
                    'customer_id' => $subscription?->stripe_customer_id ?? ($checkoutSession->customer ?? null),
                    'invoice_id' => $subscription?->latest_invoice_id ?? (is_object($latestInvoice) ? ($latestInvoice->id ?? null) : $latestInvoice),
                    'invoice_download_url' => is_object($latestInvoice) ? ($latestInvoice->invoice_pdf ?? null) : null,
                    'payment_method' => $paymentMethodLabel,
                    'transaction_timestamp' => is_object($paymentIntent) ? ($paymentIntent->created ? Carbon::createFromTimestamp($paymentIntent->created)->toISOString() : null) : null,
                ],
            ], 'Checkout session verified successfully.');
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

    /**
     * @return \Illuminate\Support\Collection<int, array{addon_id: string, quantity: int}>
     */
    private function normalizeSelectedAddons(array $validated)
    {
        if (!empty($validated['addons']) && is_array($validated['addons'])) {
            return collect($validated['addons'])
                ->map(fn (array $addon): array => [
                    'addon_id' => $addon['addon_id'],
                    'quantity' => max(1, (int) ($addon['quantity'] ?? 1)),
                ])
                ->values();
        }

        $addonIds = collect($validated['addon_ids'] ?? [])->values();
        $quantities = $validated['quantities'] ?? [];

        return $addonIds->map(fn (string $addonId): array => [
            'addon_id' => $addonId,
            'quantity' => max(1, (int) ($quantities[$addonId] ?? 1)),
        ]);
    }

    private function normalizeBillingCycle(string $billingCycle): string
    {
        return $billingCycle === 'annual' ? 'yearly' : $billingCycle;
    }

    private function planAmount(SubscriptionPlan $plan, string $billingCycle): float
    {
        return (float) ($billingCycle === 'yearly'
            ? ($plan->yearly_price ?? $plan->monthly_price ?? 0)
            : ($plan->monthly_price ?? 0));
    }

    private function addonAmount(Addon $addon, string $billingCycle): float
    {
        return (float) match ($addon->pricing_type) {
            'yearly' => $addon->yearly_price ?? $addon->monthly_price ?? $addon->one_time_price ?? 0,
            'monthly' => $addon->monthly_price ?? $addon->one_time_price ?? 0,
            'one_time' => $addon->one_time_price ?? 0,
            default => $billingCycle === 'yearly'
                ? ($addon->yearly_price ?? $addon->monthly_price ?? $addon->one_time_price ?? 0)
                : ($addon->monthly_price ?? $addon->one_time_price ?? 0),
        };
    }
}
