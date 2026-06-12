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
        $organizations = Organization::query()->with('plan')->get()->keyBy('slug');
        $plans = SubscriptionPlan::query()->get()->keyBy('name');

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
                'key' => 'welcome-email',
                'name' => 'Welcome Email',
                'subject' => 'Welcome to Briksy, {{name}}',
                'body' => 'Hi {{name}}, your Briksy account is ready.',
                'variables' => ['name'],
                'status' => 'active',
            ],
            [
                'key' => 'order-confirmation',
                'name' => 'Order Confirmation',
                'subject' => 'Order {{order_number}} confirmed',
                'body' => 'Thanks {{name}}. Your order total is {{total_amount}} {{currency}}.',
                'variables' => ['name', 'order_number', 'total_amount', 'currency'],
                'status' => 'active',
            ],
            [
                'key' => 'plan-request-review',
                'name' => 'Plan Request Review',
                'subject' => 'Your plan request is {{status}}',
                'body' => 'Hello {{company_name}}, your plan request has been {{status}}.',
                'variables' => ['company_name', 'status'],
                'status' => 'active',
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::withTrashed()->updateOrCreate(
                ['key' => $template['key']],
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
            $plan = $organization->plan ?? $plans->get('Growth') ?? $plans->first();
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
            $plan = $organization->plan ?? $plans->get('Starter') ?? $plans->first();
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
}
