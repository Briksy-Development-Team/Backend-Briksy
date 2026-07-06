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
                'name' => 'Enterprise',
                'description' => 'Advanced controls for large organizations with high volume operations.',
                'monthly_price' => 199.00,
                'yearly_price' => 1990.00,
                'currency' => 'AUD',
                'billing_enabled' => true,
                'trial_days' => 30,
                'price' => 19900,
                'property_limit' => 500,
                'popular' => false,
                'staff_seat_limit' => 50,
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
        ];

        $planModels = [];

        foreach ($plans as $plan) {
            $planModels[$plan['name']] = SubscriptionPlan::withTrashed()->updateOrCreate(
                ['name' => $plan['name']],
                [
                    'stripe_price_id' => 'internal-' . Str::slug($plan['name']) . '-legacy',
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

            if (method_exists($planModels[$plan['name']], 'trashed') && $planModels[$plan['name']]->trashed()) {
                $planModels[$plan['name']]->restore();
                $planModels[$plan['name']]->refresh();
            }
        }

        $organizationPlanMap = [
            'harborview-realty' => 'Growth',
            'sunrise-property-group' => 'Starter',
            'willowbrook-aged-care' => 'Enterprise',
            'brightpath-home-services' => 'Starter',
        ];

        foreach ($organizationPlanMap as $slug => $planName) {
            $plan = $planModels[$planName] ?? null;
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
