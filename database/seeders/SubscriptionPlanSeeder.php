<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'plan_family' => 'property_owner',
                'name' => 'Starter',
                'description' => 'Best for small teams that want to get started quickly.',
                'monthly_price' => 49.00,
                'yearly_price' => 490.00,
                'currency' => 'AUD',
                'billing_enabled' => true,
                'trial_days' => 14,
                'price' => 4900,
                'property_limit' => 25,
                'popular' => false,
                'staff_seat_limit' => 3,
                'has_visitor_analytics' => false,
                'ranking_priority' => 1,
                'features' => [
                    ['name' => 'Property Listings', 'enabled' => true, 'value' => 25],
                    ['name' => 'Featured Listings', 'enabled' => false, 'value' => null],
                    ['name' => 'Agent Profiles', 'enabled' => true, 'value' => null],
                    ['name' => 'Advanced Analytics', 'enabled' => false, 'value' => null],
                    ['name' => 'Priority Support', 'enabled' => false, 'value' => null],
                    ['name' => 'CRM Integration', 'enabled' => false, 'value' => 0],
                    ['name' => 'Lead Management', 'enabled' => true, 'value' => null],
                    ['name' => 'Custom Branding', 'enabled' => false, 'value' => null],
                ],
            ],
            [
                'plan_family' => 'property_owner',
                'name' => 'Growth',
                'description' => 'Built for growing teams that need more capacity and analytics.',
                'monthly_price' => 99.00,
                'yearly_price' => 990.00,
                'currency' => 'AUD',
                'billing_enabled' => true,
                'trial_days' => 14,
                'price' => 9900,
                'property_limit' => 100,
                'popular' => true,
                'staff_seat_limit' => 10,
                'has_visitor_analytics' => true,
                'ranking_priority' => 2,
                'features' => [
                    ['name' => 'Property Listings', 'enabled' => true, 'value' => 100],
                    ['name' => 'Featured Listings', 'enabled' => true, 'value' => null],
                    ['name' => 'Agent Profiles', 'enabled' => true, 'value' => null],
                    ['name' => 'Advanced Analytics', 'enabled' => true, 'value' => null],
                    ['name' => 'Priority Support', 'enabled' => true, 'value' => null],
                    ['name' => 'CRM Integration', 'enabled' => false, 'value' => 0],
                    ['name' => 'Lead Management', 'enabled' => true, 'value' => null],
                    ['name' => 'Custom Branding', 'enabled' => true, 'value' => null],
                ],
            ],
            [
                'plan_family' => 'property_owner',
                'name' => 'Elite',
                'description' => 'Advanced controls for established property businesses.',
                'monthly_price' => 199.00,
                'yearly_price' => 1990.00,
                'currency' => 'AUD',
                'billing_enabled' => true,
                'trial_days' => 30,
                'price' => 19900,
                'property_limit' => 500,
                'popular' => false,
                'staff_seat_limit' => 25,
                'has_visitor_analytics' => true,
                'ranking_priority' => 3,
                'features' => [
                    ['name' => 'Property Listings', 'enabled' => true, 'value' => 500],
                    ['name' => 'Featured Listings', 'enabled' => true, 'value' => null],
                    ['name' => 'Agent Profiles', 'enabled' => true, 'value' => null],
                    ['name' => 'Advanced Analytics', 'enabled' => true, 'value' => null],
                    ['name' => 'Priority Support', 'enabled' => true, 'value' => null],
                    ['name' => 'CRM Integration', 'enabled' => true, 'value' => 0],
                    ['name' => 'Lead Management', 'enabled' => true, 'value' => null],
                    ['name' => 'Custom Branding', 'enabled' => true, 'value' => null],
                ],
            ],
            [
                'plan_family' => 'property_owner',
                'name' => 'Enterprise',
                'description' => 'Full-scale plan for high-volume property businesses.',
                'monthly_price' => 499.00,
                'yearly_price' => 4990.00,
                'currency' => 'AUD',
                'billing_enabled' => true,
                'trial_days' => 30,
                'price' => 49900,
                'property_limit' => 9999,
                'popular' => false,
                'staff_seat_limit' => 50,
                'has_visitor_analytics' => true,
                'ranking_priority' => 4,
                'features' => [
                    ['name' => 'Property Listings', 'enabled' => true, 'value' => 9999],
                    ['name' => 'Featured Listings', 'enabled' => true, 'value' => null],
                    ['name' => 'Agent Profiles', 'enabled' => true, 'value' => null],
                    ['name' => 'Advanced Analytics', 'enabled' => true, 'value' => null],
                    ['name' => 'Priority Support', 'enabled' => true, 'value' => null],
                    ['name' => 'CRM Integration', 'enabled' => true, 'value' => 0],
                    ['name' => 'Lead Management', 'enabled' => true, 'value' => null],
                    ['name' => 'Custom Branding', 'enabled' => true, 'value' => null],
                ],
            ],
            [
                'plan_family' => 'trades_professional',
                'name' => 'Starter',
                'description' => 'A simple starting point for solo traders and independent professionals.',
                'monthly_price' => 59.00,
                'yearly_price' => 590.00,
                'currency' => 'AUD',
                'billing_enabled' => true,
                'trial_days' => 14,
                'price' => 5900,
                'property_limit' => 5,
                'popular' => false,
                'staff_seat_limit' => 1,
                'has_visitor_analytics' => false,
                'ranking_priority' => 1,
                'features' => [
                    ['name' => 'Professional Business Profile', 'enabled' => true, 'value' => null],
                    ['name' => 'Verification Level', 'enabled' => true, 'value' => null],
                    ['name' => 'Service Areas', 'enabled' => true, 'value' => 5],
                    ['name' => 'Map View', 'enabled' => true, 'value' => null],
                    ['name' => 'List View', 'enabled' => true, 'value' => null],
                    ['name' => 'Service Categories', 'enabled' => false, 'value' => null],
                    ['name' => 'Portfolio Photos', 'enabled' => true, 'value' => 5],
                    ['name' => 'Portfolio Videos', 'enabled' => false, 'value' => null],
                    ['name' => 'Business Logo', 'enabled' => true, 'value' => null],
                    ['name' => 'Cover Image', 'enabled' => true, 'value' => null],
                    ['name' => 'Business Description', 'enabled' => true, 'value' => 1500],
                    ['name' => 'Website', 'enabled' => true, 'value' => null],
                    ['name' => 'Social Media Links', 'enabled' => true, 'value' => null],
                    ['name' => 'Team Members', 'enabled' => true, 'value' => 1],
                    ['name' => 'Google Reviews', 'enabled' => true, 'value' => null],
                    ['name' => 'Get in Touch Enquiry Form', 'enabled' => true, 'value' => null],
                    ['name' => 'Email Notifications', 'enabled' => true, 'value' => null],
                    ['name' => 'Mobile App Notifications', 'enabled' => false, 'value' => null],
                    ['name' => 'Homepage Featured Business', 'enabled' => false, 'value' => null],
                    ['name' => 'Featured Search Placement', 'enabled' => false, 'value' => null],
                    ['name' => 'Lead History', 'enabled' => true, 'value' => null],
                    ['name' => 'Performance Analytics', 'enabled' => false, 'value' => null],
                    ['name' => 'Monthly Performance Report', 'enabled' => false, 'value' => null],
                    ['name' => 'Export Leads', 'enabled' => false, 'value' => null],
                    ['name' => 'Promotional Offers & Coupons', 'enabled' => false, 'value' => 0],
                    ['name' => 'AI Business Insights', 'enabled' => false, 'value' => null],
                    ['name' => 'AI Lead Recommendations', 'enabled' => false, 'value' => null],
                ],
            ],
            [
                'plan_family' => 'trades_professional',
                'name' => 'Growth',
                'description' => 'Best for growing businesses that need stronger visibility and lead handling.',
                'monthly_price' => 129.00,
                'yearly_price' => 1290.00,
                'currency' => 'AUD',
                'billing_enabled' => true,
                'trial_days' => 14,
                'price' => 12900,
                'property_limit' => 20,
                'popular' => true,
                'staff_seat_limit' => 3,
                'has_visitor_analytics' => true,
                'ranking_priority' => 2,
                'features' => [
                    ['name' => 'Professional Business Profile', 'enabled' => true, 'value' => null],
                    ['name' => 'Verification Level', 'enabled' => true, 'value' => null],
                    ['name' => 'Service Areas', 'enabled' => true, 'value' => 20],
                    ['name' => 'Map View', 'enabled' => true, 'value' => null],
                    ['name' => 'List View', 'enabled' => true, 'value' => null],
                    ['name' => 'Service Categories', 'enabled' => false, 'value' => null],
                    ['name' => 'Portfolio Photos', 'enabled' => true, 'value' => 10],
                    ['name' => 'Portfolio Videos', 'enabled' => true, 'value' => 2],
                    ['name' => 'Business Logo', 'enabled' => true, 'value' => null],
                    ['name' => 'Cover Image', 'enabled' => true, 'value' => null],
                    ['name' => 'Business Description', 'enabled' => true, 'value' => 1500],
                    ['name' => 'Website', 'enabled' => true, 'value' => null],
                    ['name' => 'Social Media Links', 'enabled' => true, 'value' => null],
                    ['name' => 'Team Members', 'enabled' => true, 'value' => 3],
                    ['name' => 'Google Reviews', 'enabled' => true, 'value' => null],
                    ['name' => 'Get in Touch Enquiry Form', 'enabled' => true, 'value' => null],
                    ['name' => 'Email Notifications', 'enabled' => true, 'value' => null],
                    ['name' => 'Mobile App Notifications', 'enabled' => true, 'value' => null],
                    ['name' => 'Homepage Featured Business', 'enabled' => false, 'value' => null],
                    ['name' => 'Featured Search Placement', 'enabled' => false, 'value' => null],
                    ['name' => 'Lead History', 'enabled' => true, 'value' => null],
                    ['name' => 'Performance Analytics', 'enabled' => true, 'value' => null],
                    ['name' => 'Monthly Performance Report', 'enabled' => true, 'value' => null],
                    ['name' => 'Export Leads', 'enabled' => true, 'value' => null],
                    ['name' => 'Promotional Offers & Coupons', 'enabled' => true, 'value' => 2],
                    ['name' => 'AI Business Insights', 'enabled' => false, 'value' => null],
                    ['name' => 'AI Lead Recommendations', 'enabled' => false, 'value' => null],
                ],
            ],
            [
                'plan_family' => 'trades_professional',
                'name' => 'Elite',
                'description' => 'For established operators that need premium exposure and advanced reporting.',
                'monthly_price' => 199.00,
                'yearly_price' => 1990.00,
                'currency' => 'AUD',
                'billing_enabled' => true,
                'trial_days' => 30,
                'price' => 19900,
                'property_limit' => 35,
                'popular' => false,
                'staff_seat_limit' => 5,
                'has_visitor_analytics' => true,
                'ranking_priority' => 3,
                'features' => [
                    ['name' => 'Professional Business Profile', 'enabled' => true, 'value' => null],
                    ['name' => 'Verification Level', 'enabled' => true, 'value' => null],
                    ['name' => 'Service Areas', 'enabled' => true, 'value' => 35],
                    ['name' => 'Map View', 'enabled' => true, 'value' => null],
                    ['name' => 'List View', 'enabled' => true, 'value' => null],
                    ['name' => 'Service Categories', 'enabled' => true, 'value' => 5],
                    ['name' => 'Portfolio Photos', 'enabled' => true, 'value' => 20],
                    ['name' => 'Portfolio Videos', 'enabled' => true, 'value' => 5],
                    ['name' => 'Business Logo', 'enabled' => true, 'value' => null],
                    ['name' => 'Cover Image', 'enabled' => true, 'value' => null],
                    ['name' => 'Business Description', 'enabled' => true, 'value' => 1500],
                    ['name' => 'Website', 'enabled' => true, 'value' => null],
                    ['name' => 'Social Media Links', 'enabled' => true, 'value' => null],
                    ['name' => 'Team Members', 'enabled' => true, 'value' => 5],
                    ['name' => 'Google Reviews', 'enabled' => true, 'value' => null],
                    ['name' => 'Get in Touch Enquiry Form', 'enabled' => true, 'value' => null],
                    ['name' => 'Email Notifications', 'enabled' => true, 'value' => null],
                    ['name' => 'Mobile App Notifications', 'enabled' => true, 'value' => null],
                    ['name' => 'Homepage Featured Business', 'enabled' => true, 'value' => null],
                    ['name' => 'Featured Search Placement', 'enabled' => true, 'value' => null],
                    ['name' => 'Lead History', 'enabled' => true, 'value' => null],
                    ['name' => 'Performance Analytics', 'enabled' => true, 'value' => null],
                    ['name' => 'Monthly Performance Report', 'enabled' => true, 'value' => null],
                    ['name' => 'Export Leads', 'enabled' => true, 'value' => null],
                    ['name' => 'Promotional Offers & Coupons', 'enabled' => true, 'value' => 5],
                    ['name' => 'AI Business Insights', 'enabled' => true, 'value' => null],
                    ['name' => 'AI Lead Recommendations', 'enabled' => false, 'value' => null],
                ],
            ],
            [
                'plan_family' => 'trades_professional',
                'name' => 'Enterprise',
                'description' => 'Unlimited state-wide coverage with advanced AI and reporting.',
                'monthly_price' => 499.00,
                'yearly_price' => 4990.00,
                'currency' => 'AUD',
                'billing_enabled' => true,
                'trial_days' => 30,
                'price' => 49900,
                'property_limit' => 9999,
                'popular' => false,
                'staff_seat_limit' => 10,
                'has_visitor_analytics' => true,
                'ranking_priority' => 4,
                'features' => [
                    ['name' => 'Professional Business Profile', 'enabled' => true, 'value' => null],
                    ['name' => 'Verification Level', 'enabled' => true, 'value' => null],
                    ['name' => 'Service Areas', 'enabled' => true, 'value' => null],
                    ['name' => 'Map View', 'enabled' => true, 'value' => null],
                    ['name' => 'List View', 'enabled' => true, 'value' => null],
                    ['name' => 'Service Categories', 'enabled' => true, 'value' => 10],
                    ['name' => 'Portfolio Photos', 'enabled' => true, 'value' => 30],
                    ['name' => 'Portfolio Videos', 'enabled' => true, 'value' => 10],
                    ['name' => 'Business Logo', 'enabled' => true, 'value' => null],
                    ['name' => 'Cover Image', 'enabled' => true, 'value' => null],
                    ['name' => 'Business Description', 'enabled' => true, 'value' => 1500],
                    ['name' => 'Website', 'enabled' => true, 'value' => null],
                    ['name' => 'Social Media Links', 'enabled' => true, 'value' => null],
                    ['name' => 'Team Members', 'enabled' => true, 'value' => 10],
                    ['name' => 'Google Reviews', 'enabled' => true, 'value' => null],
                    ['name' => 'Get in Touch Enquiry Form', 'enabled' => true, 'value' => null],
                    ['name' => 'Email Notifications', 'enabled' => true, 'value' => null],
                    ['name' => 'Mobile App Notifications', 'enabled' => true, 'value' => null],
                    ['name' => 'Homepage Featured Business', 'enabled' => true, 'value' => null],
                    ['name' => 'Featured Search Placement', 'enabled' => true, 'value' => null],
                    ['name' => 'Lead History', 'enabled' => true, 'value' => null],
                    ['name' => 'Performance Analytics', 'enabled' => true, 'value' => null],
                    ['name' => 'Monthly Performance Report', 'enabled' => true, 'value' => null],
                    ['name' => 'Export Leads', 'enabled' => true, 'value' => null],
                    ['name' => 'Promotional Offers & Coupons', 'enabled' => true, 'value' => 10],
                    ['name' => 'AI Business Insights', 'enabled' => true, 'value' => null],
                    ['name' => 'AI Lead Recommendations', 'enabled' => true, 'value' => null],
                ],
            ],
        ];

        $planModels = [];

        foreach ($plans as $plan) {
            $planModels[$plan['plan_family'].':'.$plan['name']] = SubscriptionPlan::withTrashed()->updateOrCreate(
                [
                    'plan_family' => $plan['plan_family'],
                    'name' => $plan['name'],
                ],
                [
                    'stripe_price_id' => 'internal-' . Str::slug($plan['plan_family'].'-'.$plan['name']) . '-legacy',
                    'description' => $plan['description'],
                    'monthly_price' => $plan['monthly_price'],
                    'yearly_price' => $plan['yearly_price'],
                    'currency' => $plan['currency'],
                    'billing_enabled' => $plan['billing_enabled'],
                    'trial_days' => $plan['trial_days'],
                    'price' => $plan['price'],
                    'property_limit' => $plan['property_limit'],
                    'popular' => $plan['popular'],
                    'features' => $plan['features'],
                    'permissions' => [],
                    'is_active' => true,
                    'staff_seat_limit' => $plan['staff_seat_limit'],
                    'has_visitor_analytics' => $plan['has_visitor_analytics'],
                    'ranking_priority' => $plan['ranking_priority'],
                ]
            );

            if (method_exists($planModels[$plan['plan_family'].':'.$plan['name']], 'trashed') && $planModels[$plan['plan_family'].':'.$plan['name']]->trashed()) {
                $planModels[$plan['plan_family'].':'.$plan['name']]->restore();
                $planModels[$plan['plan_family'].':'.$plan['name']]->refresh();
            }
        }

        $organizationPlanMap = [
            'harborview-realty' => ['property_owner', 'Growth'],
            'sunrise-property-group' => ['property_owner', 'Starter'],
            'willowbrook-aged-care' => ['property_owner', 'Enterprise'],
            'brightpath-home-services' => ['property_owner', 'Starter'],
        ];

        foreach ($organizationPlanMap as $slug => [$family, $planName]) {
            $plan = $planModels[$family.':'.$planName] ?? null;
            if (!$plan) {
                continue;
            }

            $organizationId = DB::table('organizations')->where('slug', $slug)->value('id');
            if (!$organizationId) {
                continue;
            }

            DB::table('organizations')
                ->where('slug', $slug)
                ->update([
                    'plan_id' => $plan->id,
                    'updated_at' => now(),
                ]);

            $subscriptionPayload = [
                'subscription_plan_id' => $plan->id,
                'stripe_subscription_id' => 'sub_' . $slug,
                'status' => 'active',
                'current_period_start' => Carbon::now()->subDays(5),
                'current_period_end' => Carbon::now()->addDays(25),
                'updated_at' => now(),
            ];

            $existingSubscription = DB::table('subscriptions')
                ->where('organization_id', $organizationId)
                ->first();

            if ($existingSubscription) {
                DB::table('subscriptions')
                    ->where('id', $existingSubscription->id)
                    ->update($subscriptionPayload);

                continue;
            }

            DB::table('subscriptions')->insert(array_merge($subscriptionPayload, [
                'id' => (string) Str::uuid(),
                'organization_id' => $organizationId,
                'created_at' => now(),
            ]));
        }
    }
}
