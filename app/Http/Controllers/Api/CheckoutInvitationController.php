<?php

namespace App\Http\Controllers\Api;

use App\Models\CheckoutInvitation;
use App\Models\Addon;
use App\Models\Inquiry;
use App\Models\Organization;
use App\Models\OrganizationType;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\DynamicIdGeneratorService;
use App\Services\AbnLookupService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Stripe\StripeClient;

class CheckoutInvitationController extends Controller
{
    public function direct(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'uuid', 'exists:subscription_plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly,annual'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'company_name' => ['required', 'string', 'max:200'],
            'business_type' => ['required', 'in:organisation,company,solo_trader'],
            'abn_number' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:50'],
            'postcode' => ['required', 'string', 'max:10'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'addons' => ['nullable', 'array'],
            'addons.*.addon_id' => ['required', 'uuid', 'exists:addons,id'],
            'addons.*.quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $plan = SubscriptionPlan::query()->findOrFail($data['plan_id']);
        abort_unless($plan->is_active && $plan->billing_enabled !== false, 422, 'The selected plan is not available for online checkout.');

        $selectedAddons = collect($data['addons'] ?? [])->map(fn (array $addon): array => [
            'addon_id' => $addon['addon_id'],
            'quantity' => max(1, (int) ($addon['quantity'] ?? 1)),
        ])->values();
        $availableAddonIds = Addon::query()->where('is_active', true)->pluck('id');
        if ($selectedAddons->pluck('addon_id')->diff($availableAddonIds)->isNotEmpty()) {
            return response()->json(['success' => false, 'message' => 'One or more selected add-ons are not available for this plan.'], 422);
        }

        $inquiry = Inquiry::query()->create([
            'reference_no' => app(DynamicIdGeneratorService::class)->generate('inquiries'),
            'subject' => 'Online checkout — ' . $plan->name,
            'message' => 'Checkout started from the public pricing page.',
            'seeker_name' => $data['name'],
            'seeker_email' => $data['email'],
            'seeker_phone' => $data['phone'],
            'company_name' => $data['company_name'],
            'plan_id' => $plan->id,
            'lead_source' => 'pricing',
            'status' => 'payment_pending',
        ]);

        $invitation = CheckoutInvitation::query()->create([
            'inquiry_id' => $inquiry->id,
            'plan_id' => $plan->id,
            'token' => Str::random(96),
            'status' => 'active',
            'billing_cycle' => $data['billing_cycle'] === 'annual' ? 'yearly' : $data['billing_cycle'],
            'addons' => $selectedAddons->all(),
            'expires_at' => now()->addHours(2),
        ]);

        return $this->payment($request, $invitation->token);
    }

    public function inquiry(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'uuid', 'exists:subscription_plans,id'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'company_name' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
        ]);
        $plan = SubscriptionPlan::query()->findOrFail($data['plan_id']);
        abort_unless($plan->is_active && $plan->show_price === false, 422, 'This plan does not require a sales enquiry.');
        $user = $request->user('sanctum');
        $inquiry = Inquiry::query()->create([
            'reference_no' => app(DynamicIdGeneratorService::class)->generate('inquiries'),
            'user_id' => $user?->id,
            'organization_id' => $user?->organization_id,
            'subject' => 'Pricing enquiry — ' . $plan->name,
            'message' => $data['message'],
            'seeker_name' => $data['name'],
            'seeker_email' => $data['email'],
            'seeker_phone' => $data['phone'],
            'company_name' => $data['company_name'],
            'plan_id' => $plan->id,
            'lead_source' => 'pricing',
            'status' => 'new',
        ]);
        app(NotificationService::class)->notifySuperAdminTeam(
            app(NotificationService::class)->buildPayload(
                'pricing_inquiry_created',
                'New pricing inquiry',
                sprintf('%s submitted an inquiry for the %s plan.', $inquiry->seeker_name, $plan->name),
                Inquiry::class,
                $inquiry->id,
                '/super-admin/pricing-inquiries/' . $inquiry->display_id,
                'high',
                $user?->id,
            ),
            'New Briksy pricing inquiry',
            'Review inquiry'
        );
        return $this->success(['reference_no' => $inquiry->reference_no, 'id' => $inquiry->id], 'Thanks! Our team will contact you shortly.', 201);
    }

    public function show(string $token): JsonResponse
    {
        $invitation = CheckoutInvitation::query()->with(['plan', 'inquiry'])->where('token', $token)->first();
        if (!$invitation) return response()->json(['success' => false, 'message' => 'Checkout link not found.'], 404);
        if ($invitation->status === 'active' && now()->greaterThanOrEqualTo($invitation->expires_at)) {
            $invitation->update(['status' => 'expired']);
        }

        if ($invitation->status === 'used' || $invitation->status === 'paid') {
            return response()->json(['success' => false, 'state' => 'used', 'message' => 'This checkout link has already been used.'], 410);
        }
        if ($invitation->status === 'payment_pending') {
            return response()->json(['success' => false, 'state' => 'payment_pending', 'message' => 'A payment is already being processed for this checkout link.'], 409);
        }
        if ($invitation->status === 'expired' || $invitation->status === 'cancelled') {
            return response()->json(['success' => false, 'state' => 'expired', 'message' => 'This checkout link has expired. Please contact Briksy to request a new checkout link.'], 410);
        }
        if (!$invitation->plan?->is_active) {
            return response()->json(['success' => false, 'message' => 'The selected plan is no longer available.'], 422);
        }

        return $this->success([
            'token' => $invitation->token,
            'expires_at' => $invitation->expires_at?->toISOString(),
            'status' => $invitation->status,
            'plan' => [
                'id' => $invitation->plan->id,
                'name' => $invitation->plan->name,
                'description' => $invitation->plan->description,
                'monthly_price' => (float) ($invitation->plan->monthly_price ?? 0),
                'yearly_price' => $invitation->plan->discountedYearlyPrice(),
                'currency' => $invitation->plan->currency ?? 'AUD',
                'billing_cycle' => $invitation->billing_cycle,
                'features' => $invitation->plan->features ?? [],
            ],
            'customer' => [
                'name' => $invitation->inquiry?->seeker_name,
                'email' => $invitation->inquiry?->seeker_email,
                'phone' => $invitation->inquiry?->seeker_phone,
                'company_name' => $invitation->inquiry?->company_name,
            ],
        ], 'Checkout invitation retrieved successfully.');
    }

    public function payment(Request $request, string $token): JsonResponse
    {
        $invitation = CheckoutInvitation::query()->with(['plan', 'inquiry', 'organization'])->where('token', $token)->first();
        if (!$invitation) return response()->json(['success' => false, 'message' => 'Checkout link not found.'], 404);
        if ($invitation->status === 'active' && now()->greaterThanOrEqualTo($invitation->expires_at)) $invitation->update(['status' => 'expired']);
        $claimed = CheckoutInvitation::query()->whereKey($invitation->id)->where('status', 'active')->where('expires_at', '>', now())->update(['status' => 'payment_pending']);
        if ($claimed !== 1) return response()->json(['success' => false, 'message' => $invitation->status === 'expired' ? 'This checkout link has expired. Please contact Briksy to request a new checkout link.' : 'This checkout link is already being processed or has already been used.'], 409);
        $invitation->refresh()->load(['plan', 'inquiry', 'organization']);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'company_name' => ['required', 'string', 'max:200'],
            'business_type' => ['nullable', 'in:organisation,company,solo_trader'],
            'abn_number' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:50'],
            'postcode' => ['nullable', 'string', 'max:10'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $stripeKey = config('services.stripe.secret');
        if (!$stripeKey || !class_exists(StripeClient::class)) return response()->json(['success' => false, 'message' => 'Stripe checkout is not configured.'], 422);
        $plan = $invitation->plan;
        if (!$plan?->is_active || $plan->billing_enabled === false) return response()->json(['success' => false, 'message' => 'The selected plan is no longer available for checkout.'], 422);

        try {
            $result = DB::transaction(function () use ($data, $invitation, $plan, $stripeKey): array {
            $user = User::query()->where('email', $data['email'])->first();
            if (!$user) {
                if (blank($data['password'])) throw ValidationException::withMessages(['password' => 'A password is required for a new account.']);
                $user = User::query()->create([
                    'name' => $data['name'], 'email' => $data['email'],
                    'password_hash' => $data['password'] ?? Str::random(32),
                ]);
            } else {
                $user->update(['name' => $data['name'], 'mobile_number' => $data['phone']]);
            }

            $organization = $user->organization;
            if ($organization) {
                $organization->loadMissing('organizationType');
                if ($plan->plan_family && $organization->organizationType?->plan_family && $organization->organizationType->plan_family !== $plan->plan_family) {
                    throw ValidationException::withMessages(['plan_id' => 'This plan is not available for your organization type.']);
                }
            }
            if (!$organization) {
                if (blank($data['business_type']) || blank($data['abn_number'])) {
                    throw ValidationException::withMessages(['business_type' => 'Business type and ABN are required to create a business account.', 'abn_number' => 'ABN is required to create a business account.']);
                }
                $abn = preg_replace('/\s+/', '', $data['abn_number']);
                $verification = app(AbnLookupService::class)->verify($abn, $data['business_type']);
                $typeSlug = match ($plan->plan_family) {
                    'buyers_agent' => 'real-estate-agent',
                    'builders' => 'builders',
                    'trades_professional' => 'trades-professionals',
                    default => $data['business_type'] === 'company' ? 'builders' : ($data['business_type'] === 'solo_trader' ? 'trades-professionals' : 'real-estate'),
                };
                $type = OrganizationType::query()->where('slug', $typeSlug)->first() ?? OrganizationType::query()->firstOrFail();
                $slug = Str::slug($data['company_name']);
                $base = $slug; $suffix = 1;
                while (Organization::query()->where('slug', $slug)->exists()) $slug = $base . '-' . $suffix++;
                $organization = Organization::query()->create([
                    'name' => $verification['entityName'] ?: $data['company_name'], 'entity_name' => $verification['entityName'] ?: $data['company_name'], 'slug' => $slug, 'type_id' => $type->id,
                    'business_type' => $data['business_type'] ?? 'organisation', 'contact_email' => $data['email'],
                    'contact_phone' => $data['phone'], 'abn' => $abn, 'abn_verified' => true, 'abn_verified_at' => now(),
                    'entity_type' => $verification['entityType'] ?? null, 'entity_status' => $verification['entityStatus'] ?? null, 'gst_registered' => (bool) ($verification['gstRegistered'] ?? false), 'abn_effective_from' => $verification['effectiveFrom'] ?? null, 'abn_raw_response' => $verification['rawResponse'] ?? null,
                    'address' => $data['address'] ?? null, 'state' => $data['state'] ?? null, 'postcode' => $data['postcode'] ?? null,
                    'is_verified' => false, 'subscription_status' => 'inactive', 'ranking_priority' => 1, 'avg_org_rating' => 0,
                ]);
                $user->update(['organization_id' => $organization->id]);
            }

            $adminRole = Role::query()->firstOrCreate(['name' => 'admin'], ['scope' => 'global', 'is_system' => true]);
            $user->roles()->syncWithoutDetaching([$adminRole->id => ['id' => (string) Str::uuid(), 'organization_id' => $organization->id]]);
            $invitation->update(['user_id' => $user->id, 'organization_id' => $organization->id]);

            $selectedAddons = collect($invitation->addons ?? [])->map(fn (array $addon): array => [
                'addon_id' => $addon['addon_id'],
                'quantity' => max(1, (int) ($addon['quantity'] ?? 1)),
            ])->values();
            $addons = Addon::query()->where('is_active', true)->get()->keyBy('id');
            if ($selectedAddons->pluck('addon_id')->diff($addons->keys())->isNotEmpty()) {
                throw ValidationException::withMessages(['addons' => 'One or more selected add-ons are no longer available.']);
            }

            $stripe = new StripeClient($stripeKey);
            $customerId = $organization->stripe_customer_id;
            if (!$customerId) {
                $customerId = $stripe->customers->create(['name' => $organization->name, 'email' => $organization->contact_email, 'metadata' => ['organization_id' => $organization->id]])->id;
                $organization->update(['stripe_customer_id' => $customerId]);
            }
            $cycle = $invitation->billing_cycle === 'yearly' ? 'year' : 'month';
            $amount = $invitation->billing_cycle === 'yearly' ? (float) ($plan->discountedYearlyPrice() ?? $plan->yearly_price ?? 0) : (float) ($plan->monthly_price ?? $plan->price ?? 0);
            $frontend = rtrim((string) env('FRONTEND_APP_URL', env('FRONTEND_URL', config('app.url'))), '/');
            $planAmount = $invitation->billing_cycle === 'yearly' ? (float) ($plan->discountedYearlyPrice() ?? $plan->yearly_price ?? 0) : (float) ($plan->monthly_price ?? $plan->price ?? 0);
            $addonAmount = 0.0;
            $lineItems = [[ 'price_data' => ['currency' => strtolower($plan->currency ?? 'AUD'), 'unit_amount' => (int) round($planAmount * 100), 'product_data' => ['name' => $plan->name], 'recurring' => ['interval' => $invitation->billing_cycle === 'yearly' ? 'year' : 'month']], 'quantity' => 1 ]];
            foreach ($selectedAddons as $selection) {
                $addon = $addons->get($selection['addon_id']);
                $quantity = $selection['quantity'];
                $price = $this->addonAmount($addon, $invitation->billing_cycle);
                $addonAmount += $price * $quantity;
                $addonItem = ['price_data' => ['currency' => strtolower($plan->currency ?? 'AUD'), 'unit_amount' => (int) round($price * 100), 'product_data' => ['name' => $addon->name]], 'quantity' => $quantity];
                if (in_array($addon->pricing_type, ['monthly', 'yearly'], true)) {
                    $addonItem['price_data']['recurring'] = ['interval' => $invitation->billing_cycle === 'yearly' ? 'year' : 'month'];
                }
                $lineItems[] = $addonItem;
            }
            $amount = $planAmount + $addonAmount;
            $session = $stripe->checkout->sessions->create([
                'mode' => 'subscription', 'customer' => $customerId,
                'line_items' => $lineItems,
                'success_url' => $frontend . '/checkout/invite/' . $invitation->token . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $frontend . '/checkout/invite/' . $invitation->token . '?cancelled=1',
                'metadata' => ['organization_id' => $organization->id, 'company_id' => $organization->id, 'plan_id' => $plan->id, 'billing_cycle' => $invitation->billing_cycle, 'checkout_invitation_id' => $invitation->id, 'inquiry_id' => $invitation->inquiry_id, 'addons' => $selectedAddons->toJson(), 'amount' => number_format($amount, 2, '.', ''), 'currency' => strtoupper($plan->currency ?? 'AUD')],
                'subscription_data' => ['metadata' => ['organization_id' => $organization->id, 'company_id' => $organization->id, 'plan_id' => $plan->id, 'billing_cycle' => $invitation->billing_cycle, 'checkout_invitation_id' => $invitation->id, 'inquiry_id' => $invitation->inquiry_id]],
            ]);
            $invitation->update(['stripe_checkout_session_id' => $session->id]);
            $subscription = Subscription::query()->updateOrCreate(['organization_id' => $organization->id], ['subscription_plan_id' => $plan->id, 'billing_cycle' => $invitation->billing_cycle, 'currency' => $plan->currency ?? 'AUD', 'amount' => $amount, 'stripe_customer_id' => $customerId, 'stripe_checkout_session_id' => $session->id, 'status' => 'incomplete', 'payment_status' => 'pending']);
            $subscription->addons()->delete();
            foreach ($selectedAddons as $selection) {
                $addon = $addons->get($selection['addon_id']);
                $quantity = $selection['quantity'];
                SubscriptionAddon::query()->create(['subscription_id' => $subscription->id, 'addon_id' => $addon->id, 'quantity' => $quantity, 'amount' => $this->addonAmount($addon, $invitation->billing_cycle) * $quantity, 'billing_cycle' => $invitation->billing_cycle]);
            }
            return ['checkout_url' => $session->url, 'session_id' => $session->id];
            });
        } catch (\Throwable $exception) {
            CheckoutInvitation::query()->whereKey($invitation->id)->where('status', 'payment_pending')->update(['status' => 'active']);
            throw $exception;
        }

        return $this->success($result, 'Checkout session created successfully.');
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
