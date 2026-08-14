<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use App\Models\Coupon;
use App\Models\EmailTemplate;
use App\Models\Order;
use App\Models\Organization;
use App\Models\PlatformSetting;
use App\Models\PlanRequest;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CommerceModuleSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::query()->where('email', 'superadmin@brisky.example')->first();
        $organizations = Organization::query()->with(['plan', 'organizationType'])->get()->keyBy('slug');
        $plans = SubscriptionPlan::query()->get()->keyBy(fn (SubscriptionPlan $plan): string => $plan->plan_family . ':' . $plan->name);

        $this->seedCoupons($superAdmin?->id);
        $this->seedEmailTemplates($superAdmin?->id);
        $this->seedPlatformSettings();
        $this->seedCompanySettings($organizations);
        $this->seedPlanRequests($organizations, $plans, $superAdmin?->id);
        $this->seedOrders($organizations, $plans, $superAdmin?->id);
    }

    private function seedCoupons(?string $createdBy): void
    {
        $coupons = [
            [
                'code' => 'WELCOME10',
                'name' => 'Welcome Offer',
                'description' => '10% off for new customers.',
                'discount_type' => 'percentage',
                'discount_value' => 10,
                'max_discount_amount' => 500,
                'min_order_amount' => 1000,
                'usage_limit' => 100,
                'per_user_limit' => 1,
                'status' => 'active',
            ],
            [
                'code' => 'BRIKSY500',
                'name' => 'Flat Saver',
                'description' => 'Flat discount on annual plans.',
                'discount_type' => 'fixed',
                'discount_value' => 500,
                'max_discount_amount' => null,
                'min_order_amount' => 2000,
                'usage_limit' => 50,
                'per_user_limit' => 2,
                'status' => 'active',
            ],
        ];

        foreach ($coupons as $coupon) {
            Coupon::withTrashed()->updateOrCreate(
                ['code' => $coupon['code']],
                array_merge($coupon, [
                    'created_by' => $createdBy,
                    'starts_at' => Carbon::now()->subDays(7),
                    'expires_at' => Carbon::now()->addMonths(2),
                ])
            );
        }
    }

    private function seedEmailTemplates(?string $createdBy): void
    {
        $templates = [
            [
                'key' => 'welcome_admin',
                'slug' => 'welcome_admin',
                'name' => 'Welcome Admin',
                'subject' => 'Welcome to Briksy, {{user_name}}',
                'body' => 'Hi {{user_name}}, your {{company_name}} account is ready. Visit {{app_name}} to get started.',
                'variables' => ['user_name', 'company_name', 'app_name'],
                'module' => 'auth',
                'event_key' => 'welcome_admin',
                'status' => 'active',
                'is_active' => true,
            ],
            [
                'key' => 'plan_upgrade_success',
                'slug' => 'plan_upgrade_success',
                'name' => 'Plan Upgrade Success',
                'subject' => 'Your plan upgrade is successful',
                'body' => 'Hello {{company_name}}, your {{plan_name}} plan is now active on {{billing_cycle}} billing.',
                'variables' => ['company_name', 'plan_name', 'billing_cycle'],
                'module' => 'billing',
                'event_key' => 'plan_upgrade_success',
                'status' => 'active',
                'is_active' => true,
            ],
            [
                'key' => 'subscription_payment_success',
                'slug' => 'subscription_payment_success',
                'name' => 'Subscription Payment Success',
                'subject' => 'Payment received for {{plan_name}}',
                'body' => 'Thanks {{company_name}}. We received {{amount}} for your {{billing_cycle}} subscription.',
                'variables' => ['company_name', 'plan_name', 'amount', 'billing_cycle'],
                'module' => 'billing',
                'event_key' => 'subscription_payment_success',
                'status' => 'active',
                'is_active' => true,
            ],
            [
                'key' => 'subscription_payment_failed',
                'slug' => 'subscription_payment_failed',
                'name' => 'Subscription Payment Failed',
                'subject' => 'Payment failed for {{plan_name}}',
                'body' => 'Hello {{company_name}}, we could not process {{amount}} for your {{billing_cycle}} subscription.',
                'variables' => ['company_name', 'plan_name', 'amount', 'billing_cycle'],
                'module' => 'billing',
                'event_key' => 'subscription_payment_failed',
                'status' => 'active',
                'is_active' => true,
            ],
            [
                'key' => 'inquiry_created',
                'slug' => 'inquiry_created',
                'name' => 'Inquiry Created',
                'subject' => 'New inquiry received',
                'body' => 'Hi {{company_name}}, a new inquiry has been created by {{user_name}}.',
                'variables' => ['company_name', 'user_name'],
                'module' => 'inquiries',
                'event_key' => 'inquiry_created',
                'status' => 'active',
                'is_active' => true,
            ],
            [
                'key' => 'order_created',
                'slug' => 'order_created',
                'name' => 'Order Created',
                'subject' => 'Order {{order_number}} created',
                'body' => 'Thanks {{company_name}}. Your order total is {{amount}} {{billing_cycle}}.',
                'variables' => ['company_name', 'order_number', 'amount', 'billing_cycle'],
                'module' => 'orders',
                'event_key' => 'order_created',
                'status' => 'active',
                'is_active' => true,
            ],
            [
                'key' => 'password_reset',
                'slug' => 'password_reset',
                'name' => 'Password Reset',
                'subject' => 'Reset your password',
                'body' => 'Hi {{user_name}}, reset your password using the link below.',
                'variables' => ['user_name'],
                'module' => 'auth',
                'event_key' => 'password_reset',
                'status' => 'active',
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::withTrashed()->updateOrCreate(
                ['slug' => $template['slug']],
                array_merge($template, ['created_by' => $createdBy])
            );
        }
    }

    private function seedPlatformSettings(): void
    {
        $settings = [
            ['key' => 'site_name', 'value' => 'Briksy', 'type' => 'string', 'group' => 'branding', 'label' => 'Site Name', 'is_public' => true],
            ['key' => 'support_email', 'value' => 'support@brisky.example', 'type' => 'string', 'group' => 'branding', 'label' => 'Support Email', 'is_public' => true],
            ['key' => 'tax_rate', 'value' => '18', 'type' => 'number', 'group' => 'billing', 'label' => 'Tax Rate', 'is_public' => false],
            ['key' => 'maintenance_mode', 'value' => 'false', 'type' => 'boolean', 'group' => 'system', 'label' => 'Maintenance Mode', 'is_public' => false],
        ];

        foreach ($settings as $setting) {
            PlatformSetting::query()->updateOrCreate(['key' => $setting['key']], $setting);
        }
    }

    private function seedCompanySettings(iterable $organizations): void
    {
        foreach ($organizations as $organization) {
            $settings = [
                ['key' => 'company_name', 'value' => $organization->name, 'type' => 'string', 'group' => 'profile', 'label' => 'Company Name'],
                ['key' => 'contact_email', 'value' => $organization->contact_email, 'type' => 'string', 'group' => 'profile', 'label' => 'Contact Email'],
                ['key' => 'notifications_enabled', 'value' => 'true', 'type' => 'boolean', 'group' => 'notifications', 'label' => 'Notifications Enabled'],
            ];

            foreach ($settings as $setting) {
                CompanySetting::query()->updateOrCreate(
                    ['organization_id' => $organization->id, 'key' => $setting['key']],
                    $setting
                );
            }
        }
    }

    private function seedPlanRequests(iterable $organizations, $plans, ?string $createdBy): void
    {
        foreach ($organizations as $organization) {
            $plan = $this->resolvePlanForOrganization($organization, $plans);
            if (!$plan) {
                continue;
            }

            PlanRequest::withTrashed()->updateOrCreate(
                ['organization_id' => $organization->id, 'requested_plan_name' => $plan->name],
                [
                    'requested_by' => $createdBy,
                    'plan_id' => $plan->id,
                    'company_name' => $organization->name,
                    'contact_name' => $organization->name . ' Admin',
                    'contact_email' => $organization->contact_email,
                    'contact_phone' => $organization->contact_phone,
                    'billing_cycle' => 'monthly',
                    'message' => 'Sample seeded plan request for ' . $organization->name,
                    'status' => 'pending',
                ]
            );
        }
    }

    private function seedOrders(iterable $organizations, $plans, ?string $createdBy): void
    {
        foreach ($organizations as $organization) {
            $plan = $this->resolvePlanForOrganization($organization, $plans);
            if (!$plan) {
                continue;
            }

            $subtotal = (float) $plan->price;
            Order::withTrashed()->updateOrCreate(
                ['order_number' => 'ORD-' . strtoupper($organization->slug)],
                [
                    'organization_id' => $organization->id,
                    'user_id' => $createdBy,
                    'plan_id' => $plan->id,
                    'coupon_id' => null,
                    'subtotal' => $subtotal,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'total_amount' => $subtotal,
                    'currency' => 'INR',
                    'billing_cycle' => 'monthly',
                    'payment_status' => 'paid',
                    'order_status' => 'active',
                    'payment_method' => 'manual',
                    'transaction_reference' => 'TXN-' . strtoupper(Str::random(8)),
                    'starts_at' => Carbon::now()->subDays(10),
                    'ends_at' => Carbon::now()->addDays(20),
                    'notes' => 'Seeded sample order.',
                ]
            );
        }
    }

    private function resolvePlanForOrganization(Organization $organization, $plans): ?SubscriptionPlan
    {
        if ($organization->plan) {
            return $organization->plan;
        }

        $family = match ($organization->organizationType?->slug) {
            'real-estate' => 'property_owner',
            'buyers-agent' => 'buyers_agent',
            'builders' => 'builders',
            default => 'trades_professional',
        };

        $preferredPlans = $family === 'property_owner'
            ? ['Gold', 'Silver', 'Bronze', 'Platinum']
            : ($family === 'buyers_agent'
                ? ['Buyer Network', 'Buyer Assist']
                : ($family === 'builders'
                    ? ['Builder Enterprise', 'Builder Growth']
                    : ['Starter', 'Growth', 'Elite', 'Enterprise']));

        foreach ($preferredPlans as $planName) {
            $plan = $plans->get($family . ':' . $planName);
            if ($plan) {
                return $plan;
            }
        }

        return $plans->first();
    }
}
