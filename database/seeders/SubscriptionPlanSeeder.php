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
        $plans = $this->plans();
        $planModels = [];

        foreach ($plans as $plan) {
            $key = $plan['plan_family'] . ':' . $plan['name'];

            $planModels[$key] = SubscriptionPlan::withTrashed()->updateOrCreate(
                [
                    'plan_family' => $plan['plan_family'],
                    'name' => $plan['name'],
                ],
                [
                    'stripe_price_id' => 'internal-' . Str::slug($plan['plan_family'] . '-' . $plan['name']) . '-legacy',
                    'description' => $plan['description'],
                    'monthly_price' => $plan['monthly_price'],
                    'yearly_price' => $plan['yearly_price'],
                    'currency' => 'AUD',
                    'billing_enabled' => true,
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

            if (method_exists($planModels[$key], 'trashed') && $planModels[$key]->trashed()) {
                $planModels[$key]->restore();
                $planModels[$key]->refresh();
            }
        }

        $this->seedOrganizationPlans($planModels);
    }

    private function plans(): array
    {
        return [
            [
                'plan_family' => 'property_owner',
                'name' => 'Bronze',
                'description' => 'Entry-level plan for real estate teams that need a clean listing workflow and the essentials to get started.',
                'monthly_price' => 49.00,
                'yearly_price' => 490.00,
                'trial_days' => 14,
                'price' => 4900,
                'property_limit' => 25,
                'popular' => false,
                'staff_seat_limit' => 3,
                'has_visitor_analytics' => false,
                'ranking_priority' => 1,
                'features' => [
                    $this->feature('Property Listings', true, 25),
                    $this->feature('Map and List Views', true),
                    $this->feature('Buyer Enquiry Forms', true),
                    $this->feature('Lead Inbox', true),
                    $this->feature('Featured Listings', false),
                    $this->feature('Open Home Tools', false),
                    $this->feature('Team Members', true, 3),
                    $this->feature('Custom Branding', false),
                    $this->feature('Analytics Dashboard', false),
                    $this->feature('Priority Support', false),
                ],
            ],
            [
                'plan_family' => 'property_owner',
                'name' => 'Silver',
                'description' => 'For growing agencies that need more listing capacity, stronger visibility, and daily sales activity management.',
                'monthly_price' => 99.00,
                'yearly_price' => 990.00,
                'trial_days' => 14,
                'price' => 9900,
                'property_limit' => 100,
                'popular' => true,
                'staff_seat_limit' => 5,
                'has_visitor_analytics' => true,
                'ranking_priority' => 2,
                'features' => [
                    $this->feature('Property Listings', true, 100),
                    $this->feature('Map and List Views', true),
                    $this->feature('Buyer Enquiry Forms', true),
                    $this->feature('Lead Inbox', true),
                    $this->feature('Featured Listings', true),
                    $this->feature('Open Home Tools', true),
                    $this->feature('Team Members', true, 5),
                    $this->feature('Custom Branding', true),
                    $this->feature('Analytics Dashboard', true),
                    $this->feature('Priority Support', false),
                ],
            ],
            [
                'plan_family' => 'property_owner',
                'name' => 'Gold',
                'description' => 'Advanced real estate plan for established agencies with more listings, stronger reporting, and premium exposure.',
                'monthly_price' => 199.00,
                'yearly_price' => 1990.00,
                'trial_days' => 30,
                'price' => 19900,
                'property_limit' => 500,
                'popular' => false,
                'staff_seat_limit' => 10,
                'has_visitor_analytics' => true,
                'ranking_priority' => 3,
                'features' => [
                    $this->feature('Property Listings', true, 500),
                    $this->feature('Map and List Views', true),
                    $this->feature('Buyer Enquiry Forms', true),
                    $this->feature('Lead Inbox', true),
                    $this->feature('Featured Listings', true),
                    $this->feature('Open Home Tools', true),
                    $this->feature('Team Members', true, 10),
                    $this->feature('Custom Branding', true),
                    $this->feature('Analytics Dashboard', true),
                    $this->feature('Priority Support', true),
                ],
            ],
            [
                'plan_family' => 'property_owner',
                'name' => 'Platinum',
                'description' => 'Full-scale plan for high-volume agencies that want all listing, team, and reporting features enabled.',
                'monthly_price' => 499.00,
                'yearly_price' => 4990.00,
                'trial_days' => 30,
                'price' => 49900,
                'property_limit' => 9999,
                'popular' => false,
                'staff_seat_limit' => 25,
                'has_visitor_analytics' => true,
                'ranking_priority' => 4,
                'features' => [
                    $this->feature('Property Listings', true, 9999),
                    $this->feature('Map and List Views', true),
                    $this->feature('Buyer Enquiry Forms', true),
                    $this->feature('Lead Inbox', true),
                    $this->feature('Featured Listings', true),
                    $this->feature('Open Home Tools', true),
                    $this->feature('Team Members', true, 25),
                    $this->feature('Custom Branding', true),
                    $this->feature('Analytics Dashboard', true),
                    $this->feature('Priority Support', true),
                ],
            ],
            [
                'plan_family' => 'trades_professional',
                'name' => 'Starter',
                'description' => 'Simple starting point for solo trades and independent professionals building a local presence.',
                'monthly_price' => 59.00,
                'yearly_price' => 590.00,
                'trial_days' => 14,
                'price' => 5900,
                'property_limit' => 5,
                'popular' => false,
                'staff_seat_limit' => 1,
                'has_visitor_analytics' => false,
                'ranking_priority' => 1,
                'features' => [
                    $this->feature('Business Profile', true),
                    $this->feature('Verified Badge', true),
                    $this->feature('Service Areas', true, 5),
                    $this->feature('Service Categories', true, 3),
                    $this->feature('Map and List Views', true),
                    $this->feature('Portfolio Photos', true, 5),
                    $this->feature('Portfolio Videos', false),
                    $this->feature('Team Members', true, 1),
                    $this->feature('Lead History', true),
                    $this->feature('Performance Analytics', false),
                    $this->feature('Promo Offers', false),
                    $this->feature('AI Lead Recommendations', false),
                ],
            ],
            [
                'plan_family' => 'trades_professional',
                'name' => 'Growth',
                'description' => 'Best for growing trade businesses that need more service coverage, better lead handling, and stronger visibility.',
                'monthly_price' => 129.00,
                'yearly_price' => 1290.00,
                'trial_days' => 14,
                'price' => 12900,
                'property_limit' => 20,
                'popular' => true,
                'staff_seat_limit' => 3,
                'has_visitor_analytics' => true,
                'ranking_priority' => 2,
                'features' => [
                    $this->feature('Business Profile', true),
                    $this->feature('Verified Badge', true),
                    $this->feature('Service Areas', true, 20),
                    $this->feature('Service Categories', true, 6),
                    $this->feature('Map and List Views', true),
                    $this->feature('Portfolio Photos', true, 15),
                    $this->feature('Portfolio Videos', true, 3),
                    $this->feature('Team Members', true, 3),
                    $this->feature('Lead History', true),
                    $this->feature('Performance Analytics', true),
                    $this->feature('Promo Offers', true, 2),
                    $this->feature('AI Lead Recommendations', false),
                ],
            ],
            [
                'plan_family' => 'trades_professional',
                'name' => 'Elite',
                'description' => 'For established operators that need wider reach, advanced reporting, and premium service presentation.',
                'monthly_price' => 199.00,
                'yearly_price' => 1990.00,
                'trial_days' => 30,
                'price' => 19900,
                'property_limit' => 35,
                'popular' => false,
                'staff_seat_limit' => 5,
                'has_visitor_analytics' => true,
                'ranking_priority' => 3,
                'features' => [
                    $this->feature('Business Profile', true),
                    $this->feature('Verified Badge', true),
                    $this->feature('Service Areas', true, 35),
                    $this->feature('Service Categories', true, 10),
                    $this->feature('Map and List Views', true),
                    $this->feature('Portfolio Photos', true, 25),
                    $this->feature('Portfolio Videos', true, 5),
                    $this->feature('Team Members', true, 5),
                    $this->feature('Lead History', true),
                    $this->feature('Performance Analytics', true),
                    $this->feature('Promo Offers', true, 5),
                    $this->feature('AI Lead Recommendations', true),
                ],
            ],
            [
                'plan_family' => 'trades_professional',
                'name' => 'Enterprise',
                'description' => 'Unlimited coverage for larger trade businesses with broad service areas and premium support.',
                'monthly_price' => 499.00,
                'yearly_price' => 4990.00,
                'trial_days' => 30,
                'price' => 49900,
                'property_limit' => 9999,
                'popular' => false,
                'staff_seat_limit' => 10,
                'has_visitor_analytics' => true,
                'ranking_priority' => 4,
                'features' => [
                    $this->feature('Business Profile', true),
                    $this->feature('Verified Badge', true),
                    $this->feature('Service Areas', true, null),
                    $this->feature('Service Categories', true, 25),
                    $this->feature('Map and List Views', true),
                    $this->feature('Portfolio Photos', true, 50),
                    $this->feature('Portfolio Videos', true, 10),
                    $this->feature('Team Members', true, 10),
                    $this->feature('Lead History', true),
                    $this->feature('Performance Analytics', true),
                    $this->feature('Promo Offers', true, 10),
                    $this->feature('AI Lead Recommendations', true),
                ],
            ],
            [
                'plan_family' => 'buyers_agent',
                'name' => 'Buyer Assist',
                'description' => 'For buyers agents who manage a small number of client briefs and want a clean, simple workflow.',
                'monthly_price' => 89.00,
                'yearly_price' => 890.00,
                'trial_days' => 10,
                'price' => 8900,
                'property_limit' => 20,
                'popular' => false,
                'staff_seat_limit' => 2,
                'has_visitor_analytics' => false,
                'ranking_priority' => 1,
                'features' => [
                    $this->feature('Buyer Briefs', true, 20),
                    $this->feature('Saved Searches', true),
                    $this->feature('Property Shortlists', true),
                    $this->feature('Lead Inbox', true),
                    $this->feature('Client Updates', true),
                    $this->feature('Private Notes', true),
                    $this->feature('Team Members', true, 2),
                    $this->feature('CRM Sync', false),
                    $this->feature('Analytics Dashboard', false),
                    $this->feature('Priority Support', false),
                ],
            ],
            [
                'plan_family' => 'buyers_agent',
                'name' => 'Buyer Network',
                'description' => 'For established buyers agents that need more briefs, stronger collaboration, and reporting.',
                'monthly_price' => 149.00,
                'yearly_price' => 1490.00,
                'trial_days' => 14,
                'price' => 14900,
                'property_limit' => 60,
                'popular' => true,
                'staff_seat_limit' => 5,
                'has_visitor_analytics' => true,
                'ranking_priority' => 2,
                'features' => [
                    $this->feature('Buyer Briefs', true, 60),
                    $this->feature('Saved Searches', true),
                    $this->feature('Property Shortlists', true),
                    $this->feature('Lead Inbox', true),
                    $this->feature('Client Updates', true),
                    $this->feature('Private Notes', true),
                    $this->feature('Team Members', true, 5),
                    $this->feature('CRM Sync', true),
                    $this->feature('Analytics Dashboard', true),
                    $this->feature('Priority Support', true),
                ],
            ],
            [
                'plan_family' => 'builders',
                'name' => 'Builder Growth',
                'description' => 'For builders and project teams managing a smaller project pipeline and client communications.',
                'monthly_price' => 129.00,
                'yearly_price' => 1290.00,
                'trial_days' => 10,
                'price' => 12900,
                'property_limit' => 10,
                'popular' => false,
                'staff_seat_limit' => 3,
                'has_visitor_analytics' => false,
                'ranking_priority' => 1,
                'features' => [
                    $this->feature('Projects', true, 10),
                    $this->feature('Project Listings', true),
                    $this->feature('Client Updates', true),
                    $this->feature('Lead Inbox', true),
                    $this->feature('Custom Branding', true),
                    $this->feature('Team Members', true, 3),
                    $this->feature('Tender Management', false),
                    $this->feature('Analytics Dashboard', false),
                    $this->feature('Priority Support', false),
                    $this->feature('Site Notes', true),
                ],
            ],
            [
                'plan_family' => 'builders',
                'name' => 'Builder Enterprise',
                'description' => 'For larger builders that need more projects, team capacity, and premium visibility.',
                'monthly_price' => 249.00,
                'yearly_price' => 2490.00,
                'trial_days' => 14,
                'price' => 24900,
                'property_limit' => 100,
                'popular' => true,
                'staff_seat_limit' => 10,
                'has_visitor_analytics' => true,
                'ranking_priority' => 2,
                'features' => [
                    $this->feature('Projects', true, 100),
                    $this->feature('Project Listings', true),
                    $this->feature('Client Updates', true),
                    $this->feature('Lead Inbox', true),
                    $this->feature('Custom Branding', true),
                    $this->feature('Team Members', true, 10),
                    $this->feature('Tender Management', true),
                    $this->feature('Analytics Dashboard', true),
                    $this->feature('Priority Support', true),
                    $this->feature('Site Notes', true),
                ],
            ],
        ];
    }

    private function seedOrganizationPlans(array $planModels): void
    {
        // plan_family is the pricing bucket; it is intentionally separate from business_type
        // and organization type so the demo can show mixed business categories on different tiers.
        $planCycles = [
            'real-estate' => ['property_owner', ['Gold', 'Silver', 'Bronze', 'Platinum']],
            'buyers-agent' => ['buyers_agent', ['Buyer Network', 'Buyer Assist']],
            'builders' => ['builders', ['Builder Enterprise', 'Builder Growth']],
            'trades-professionals' => ['trades_professional', ['Enterprise', 'Growth', 'Elite', 'Starter']],
        ];

        foreach ($planCycles as $typeSlug => [$family, $planNames]) {
            $organizations = DB::table('organizations')
                ->join('organization_types', 'organizations.type_id', '=', 'organization_types.id')
                ->where('organization_types.slug', $typeSlug)
                ->orderBy('organizations.ranking_priority')
                ->orderBy('organizations.name')
                ->select('organizations.id', 'organizations.slug')
                ->get();

            foreach ($organizations as $index => $organization) {
                $planName = $planNames[$index % count($planNames)];
                $plan = $planModels[$family . ':' . $planName] ?? null;

                if (!$plan) {
                    continue;
                }

                DB::table('organizations')
                    ->where('id', $organization->id)
                    ->update([
                        'plan_id' => $plan->id,
                        'updated_at' => now(),
                    ]);

                $subscriptionPayload = [
                    'subscription_plan_id' => $plan->id,
                    'stripe_subscription_id' => 'sub_' . $organization->slug,
                    'status' => 'active',
                    'current_period_start' => Carbon::now()->subDays(5),
                    'current_period_end' => Carbon::now()->addDays(25),
                    'updated_at' => now(),
                ];

                $existingSubscription = DB::table('subscriptions')
                    ->where('organization_id', $organization->id)
                    ->first();

                if ($existingSubscription) {
                    DB::table('subscriptions')
                        ->where('id', $existingSubscription->id)
                        ->update($subscriptionPayload);

                    continue;
                }

                DB::table('subscriptions')->insert(array_merge($subscriptionPayload, [
                    'id' => (string) Str::uuid(),
                    'organization_id' => $organization->id,
                    'created_at' => now(),
                ]));
            }
        }
    }

    private function feature(string $name, bool $enabled, ?int $value = null): array
    {
        return [
            'name' => $name,
            'enabled' => $enabled,
            'value' => $value,
        ];
    }
}
