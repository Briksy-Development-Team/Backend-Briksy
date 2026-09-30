<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Models\CheckoutInvitation;
use App\Models\Inquiry;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckoutInvitationController extends Controller
{
    public function store(Request $request, Inquiry $inquiry): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'uuid', 'exists:subscription_plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly,annual'],
        ]);
        $plan = SubscriptionPlan::query()->findOrFail($data['plan_id']);
        abort_unless($plan->is_active && $plan->billing_enabled !== false, 422, 'The selected plan is not available for checkout.');
        $cycle = $data['billing_cycle'] === 'annual' ? 'yearly' : $data['billing_cycle'];
        $invitation = CheckoutInvitation::query()->create([
            'inquiry_id' => $inquiry->id,
            'user_id' => $inquiry->user_id,
            'organization_id' => $inquiry->organization_id,
            'plan_id' => $plan->id,
            'created_by' => $request->user()->id,
            'token' => Str::random(96),
            'status' => 'active',
            'billing_cycle' => $cycle,
            'expires_at' => now()->addHours(72),
        ]);
        $inquiry->update(['status' => 'checkout_sent']);
        $payload = $this->payload($invitation->load(['plan', 'inquiry']));
        $payload['email_sent'] = $this->sendCheckoutLinkEmail($payload, $inquiry);

        return $this->success($payload, 'Checkout link created successfully.', 201);
    }

    public function show(CheckoutInvitation $checkoutInvitation): JsonResponse
    {
        return $this->success($this->payload($checkoutInvitation->load(['plan', 'inquiry'])), 'Checkout invitation retrieved successfully.');
    }

    public function cancel(CheckoutInvitation $checkoutInvitation): JsonResponse
    {
        abort_unless($checkoutInvitation->status === 'active', 422, 'Only active checkout links can be cancelled.');
        $checkoutInvitation->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        return $this->success($this->payload($checkoutInvitation->fresh()->load('plan')), 'Checkout link cancelled.');
    }

    public function newLink(Request $request, CheckoutInvitation $checkoutInvitation): JsonResponse
    {
        abort_unless($checkoutInvitation->inquiry_id, 422, 'This invitation is not linked to an inquiry.');
        $inquiry = $checkoutInvitation->inquiry;
        return $this->store($request, $inquiry);
    }

    private function payload(CheckoutInvitation $invitation): array
    {
        return [
            'id' => $invitation->id,
            'status' => $invitation->status,
            'token' => $invitation->token,
            'checkout_url' => rtrim((string) env('FRONTEND_APP_URL', env('FRONTEND_URL', config('app.url'))), '/') . '/checkout/invite/' . $invitation->token,
            'plan' => $invitation->plan ? ['id' => $invitation->plan->id, 'name' => $invitation->plan->name, 'billing_cycle' => $invitation->billing_cycle] : null,
            'customer' => ['name' => $invitation->inquiry?->seeker_name, 'email' => $invitation->inquiry?->seeker_email, 'company_name' => $invitation->inquiry?->company_name],
            'created_at' => $invitation->created_at?->toISOString(),
            'expires_at' => $invitation->expires_at?->toISOString(),
            'paid_at' => $invitation->paid_at?->toISOString(),
            'stripe_checkout_session_id' => $invitation->stripe_checkout_session_id,
        ];
    }

    private function sendCheckoutLinkEmail(array $payload, Inquiry $inquiry): bool
    {
        if (!$inquiry->seeker_email) {
            Log::warning('Checkout link created without a customer email.', ['inquiry_id' => $inquiry->id]);
            return false;
        }

        try {
            Mail::html(
                '<p>Hello ' . e($inquiry->seeker_name ?: 'there') . ',</p>'
                . '<p>Your Briksy checkout link for the <strong>' . e($payload['plan']['name'] ?? 'selected plan') . '</strong> plan is ready.</p>'
                . '<p><a href="' . e($payload['checkout_url']) . '">Open secure checkout</a></p>'
                . '<p>This link expires on ' . e((string) ($payload['expires_at'] ?? '')) . '.</p>'
                . '<p>If you were not expecting this email, please contact Briksy.</p>',
                function ($message) use ($inquiry): void {
                    $message->to($inquiry->seeker_email)->subject('Your Briksy checkout link');
                }
            );
            return true;
        } catch (\Throwable $exception) {
            Log::error('Checkout link email delivery failed.', [
                'inquiry_id' => $inquiry->id,
                'recipient' => $inquiry->seeker_email,
                'error' => $exception->getMessage(),
            ]);
            return false;
        }
    }
}
