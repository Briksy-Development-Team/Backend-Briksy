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
                'stripe_price_id' => 'price_starter_monthly',
                'price' => 4900,
                'property_limit' => 25,
                'popular' => false,
                'staff_seat_limit' => 3,
                'has_visitor_analytics' => false,
                'ranking_priority' => 1,
                'features' => [
                    ['name' => 'Property Listings', 'enabled' => true],
                    ['name' => 'Featured Listings', 'enabled' => false],
                    ['name' => 'Agent Profiles', 'enabled' => true],
                    ['name' => 'Advanced Analytics', 'enabled' => false],
                    ['name' => 'Priority Support', 'enabled' => false],
                    ['name' => 'CRM Integration', 'enabled' => false],
                    ['name' => 'Lead Management', 'enabled' => true],
                    ['name' => 'Custom Branding', 'enabled' => false],
                ],
            ],
            [
                'name' => 'Growth',
                'stripe_price_id' => 'price_growth_monthly',
                'price' => 9900,
                'property_limit' => 100,
                'popular' => true,
                'staff_seat_limit' => 10,
                'has_visitor_analytics' => true,
                'ranking_priority' => 2,
                'features' => [
                    ['name' => 'Property Listings', 'enabled' => true],
                    ['name' => 'Featured Listings', 'enabled' => true],
                    ['name' => 'Agent Profiles', 'enabled' => true],
                    ['name' => 'Advanced Analytics', 'enabled' => true],
                    ['name' => 'Priority Support', 'enabled' => true],
                    ['name' => 'CRM Integration', 'enabled' => false],
                    ['name' => 'Lead Management', 'enabled' => true],
                    ['name' => 'Custom Branding', 'enabled' => true],
                ],
            ],
            [
                'name' => 'Enterprise',
                'stripe_price_id' => 'price_enterprise_monthly',
                'price' => 19900,
                'property_limit' => 500,
                'popular' => false,
                'staff_seat_limit' => 50,
                'has_visitor_analytics' => true,
                'ranking_priority' => 3,
                'features' => [
                    ['name' => 'Property Listings', 'enabled' => true],
                    ['name' => 'Featured Listings', 'enabled' => true],
                    ['name' => 'Agent Profiles', 'enabled' => true],
                    ['name' => 'Advanced Analytics', 'enabled' => true],
                    ['name' => 'Priority Support', 'enabled' => true],
                    ['name' => 'CRM Integration', 'enabled' => true],
                    ['name' => 'Lead Management', 'enabled' => true],
                    ['name' => 'Custom Branding', 'enabled' => true],
                ],
            ],
        ];

        $planModels = [];

        foreach ($plans as $plan) {
            $planModels[$plan['name']] = SubscriptionPlan::withTrashed()->updateOrCreate(
                ['name' => $plan['name']],
                [
                    'stripe_price_id' => $plan['stripe_price_id'],
                    'price' => $plan['price'],
                    'property_limit' => $plan['property_limit'],
                    'popular' => $plan['popular'],
                    'features' => $plan['features'],
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
