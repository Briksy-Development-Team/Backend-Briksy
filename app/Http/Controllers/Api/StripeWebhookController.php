<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionPlan;
use App\Services\Webhooks\WebhookDispatcherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class StripeWebhookController extends Controller
{
    public function __construct(private readonly WebhookDispatcherService $webhookDispatcher)
    {
    }

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');

        if (!$secret) {
            return response()->json(['success' => false, 'message' => 'Stripe webhook secret is not configured.'], 422);
        }
        if (!class_exists(\Stripe\Webhook::class)) {
            return response()->json(['success' => false, 'message' => 'Stripe PHP SDK is not installed.'], 500);
        }

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\Throwable $e) {
            Log::warning('Stripe webhook signature verification failed.', [
                'message' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Invalid webhook signature.'], 400);
        }

        $object = $event->data->object;
        $eventType = $event->type;
        $metadata = (array) ($object->metadata ?? []);
        $payloadArray = json_decode($payload, true);
        $organizationId = $metadata['company_id'] ?? $metadata['organization_id'] ?? null;

        DB::transaction(function () use ($event, $eventType, $object, $metadata, $organizationId, $payloadArray): void {
            $subscription = null;
            $planId = $metadata['plan_id'] ?? null;
            $billingCycle = $this->normalizeBillingCycle($metadata['billing_cycle'] ?? 'monthly');
            $amount = isset($metadata['amount']) ? (float) $metadata['amount'] : null;
            $currency = strtoupper($metadata['currency'] ?? config('services.stripe.currency', 'AUD'));
            $resolvedOrganizationId = $organizationId;

            if (!$resolvedOrganizationId && !empty($object->customer)) {
                $resolvedOrganizationId = Organization::query()->where('stripe_customer_id', $object->customer)->value('id');
            }

            if ($resolvedOrganizationId) {
                $subscription = Subscription::query()->firstOrNew([
                    'organization_id' => $resolvedOrganizationId,
                ]);
            }

            if ($eventType === 'checkout.session.completed') {
                $subscriptionId = $object->subscription ?? null;
                if ($subscription && $subscriptionId) {
                    $stripe = new StripeClient(config('services.stripe.secret'));
                    $stripeSubscription = $stripe->subscriptions->retrieve($subscriptionId, []);
                    $plan = $planId ? SubscriptionPlan::query()->find($planId) : null;
                    $resolvedAmount = $amount ?? ((float) ($stripeSubscription->items->data[0]->price->unit_amount ?? 0)) / 100;

                    $subscription->fill([
                        'subscription_plan_id' => $plan?->id ?? $subscription->subscription_plan_id,
                        'billing_cycle' => $billingCycle,
                        'currency' => strtoupper($stripeSubscription->currency ?? $currency),
                        'amount' => $resolvedAmount,
                        'stripe_customer_id' => $object->customer ?? $subscription->stripe_customer_id,
                        'stripe_subscription_id' => $stripeSubscription->id,
                        'stripe_checkout_session_id' => $object->id,
                        'status' => $stripeSubscription->status ?? 'active',
                        'payment_status' => $object->payment_status ?? 'paid',
                        'current_period_start' => isset($stripeSubscription->current_period_start) ? Carbon::createFromTimestamp($stripeSubscription->current_period_start) : null,
                        'current_period_end' => isset($stripeSubscription->current_period_end) ? Carbon::createFromTimestamp($stripeSubscription->current_period_end) : null,
                    ]);
                    $subscription->save();

                    if ($resolvedOrganizationId && $plan) {
                        Organization::query()
                            ->where('id', $resolvedOrganizationId)
                            ->update([
                                'plan_id' => $plan->id,
                                'subscription_status' => $stripeSubscription->status ?? 'active',
                                'subscription_activated_at' => now(),
                            ]);
                    }

                    if ($resolvedOrganizationId) {
                        $this->webhookDispatcher->dispatch(
                            'subscription.created',
                            [
                                'subscription_id' => $subscription->id,
                                'stripe_subscription_id' => $stripeSubscription->id,
                                'plan_id' => $subscription->subscription_plan_id,
                                'status' => $subscription->status,
                                'payment_status' => $subscription->payment_status,
                                'billing_cycle' => $billingCycle,
                            ],
                            Organization::query()->find($resolvedOrganizationId),
                            null,
                            $event->id
                        );
                    }
                }
            }

            if (in_array($eventType, ['customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.deleted'], true)) {
                if (!$resolvedOrganizationId && !empty($object->customer)) {
                    $resolvedOrganizationId = Organization::query()->where('stripe_customer_id', $object->customer)->value('id');
                }

                if ($resolvedOrganizationId) {
                    $plan = $planId ? SubscriptionPlan::query()->find($planId) : null;
                    $subscription = Subscription::query()->firstOrNew(['organization_id' => $resolvedOrganizationId]);
                    $resolvedAmount = $amount ?? ((float) ($object->items->data[0]->price->unit_amount ?? 0)) / 100;
                    $subscriptionStatus = $object->status ?? 'active';
                    $subscription->fill([
                        'subscription_plan_id' => $plan?->id ?? $subscription->subscription_plan_id,
                        'billing_cycle' => $billingCycle,
                        'currency' => strtoupper($object->currency ?? $currency),
                        'amount' => $resolvedAmount,
                        'stripe_customer_id' => $object->customer ?? $subscription->stripe_customer_id,
                        'stripe_subscription_id' => $object->id,
                        'status' => $subscriptionStatus,
                        'payment_status' => $eventType === 'customer.subscription.deleted' ? 'cancelled' : 'paid',
                        'current_period_start' => isset($object->current_period_start) ? Carbon::createFromTimestamp($object->current_period_start) : null,
                        'current_period_end' => isset($object->current_period_end) ? Carbon::createFromTimestamp($object->current_period_end) : null,
                    ]);
                    $subscription->save();

                    if ($plan && in_array($subscriptionStatus, ['active', 'trialing'], true)) {
                        Organization::query()
                            ->where('id', $resolvedOrganizationId)
                            ->update([
                                'plan_id' => $plan->id,
                                'subscription_status' => $subscriptionStatus,
                                'subscription_activated_at' => now(),
                            ]);
                    }

                    if ($resolvedOrganizationId) {
                        $webhookEvent = $eventType === 'customer.subscription.deleted' ? 'subscription.cancelled' : 'subscription.updated';
                        $this->webhookDispatcher->dispatch(
                            $webhookEvent,
                            [
                                'subscription_id' => $subscription->id,
                                'stripe_subscription_id' => $object->id,
                                'plan_id' => $subscription->subscription_plan_id,
                                'status' => $subscription->status,
                                'payment_status' => $subscription->payment_status,
                                'billing_cycle' => $billingCycle,
                            ],
                            Organization::query()->find($resolvedOrganizationId),
                            null,
                            $event->id
                        );
                    }
                }
            }

            if (in_array($eventType, ['invoice.payment_succeeded', 'invoice.payment_failed'], true) && $resolvedOrganizationId) {
                $subscription = Subscription::query()->where('organization_id', $resolvedOrganizationId)->first();
                if ($subscription) {
                    $subscription->update([
                        'latest_invoice_id' => $object->id,
                        'payment_status' => $eventType === 'invoice.payment_succeeded' ? 'paid' : 'failed',
                        'status' => $eventType === 'invoice.payment_succeeded' ? 'active' : 'past_due',
                    ]);
                }

                $this->webhookDispatcher->dispatch(
                    $eventType === 'invoice.payment_succeeded' ? 'invoice.paid' : 'invoice.failed',
                    [
                        'subscription_id' => $subscription?->id,
                        'invoice_id' => $object->id,
                        'stripe_subscription_id' => $subscription?->stripe_subscription_id,
                        'status' => $eventType === 'invoice.payment_succeeded' ? 'paid' : 'failed',
                        'amount_paid' => isset($object->amount_paid) ? ((float) $object->amount_paid) / 100 : null,
                        'currency' => strtoupper((string) ($object->currency ?? $currency)),
                    ],
                    Organization::query()->find($resolvedOrganizationId),
                    null,
                    $event->id
                );
            }

            SubscriptionEvent::query()->create([
                'organization_id' => $resolvedOrganizationId,
                'subscription_id' => $subscription?->id,
                'event_type' => $eventType,
                'stripe_event_id' => $event->id,
                'payload' => $payloadArray,
                'status' => 'processed',
            ]);
        });

        Log::info('Stripe webhook processed.', [
            'event_type' => $eventType,
            'organization_id' => $organizationId,
            'stripe_event_id' => $event->id,
        ]);

        return response()->json(['success' => true]);
    }

    private function normalizeBillingCycle(string $billingCycle): string
    {
        return $billingCycle === 'annual' ? 'yearly' : $billingCycle;
    }
}
