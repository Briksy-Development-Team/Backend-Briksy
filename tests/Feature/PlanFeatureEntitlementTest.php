<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationType;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlanFeatureEntitlementTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('tradesPlans')]
    public function test_approved_trades_plans_expose_profile_and_verified_badge_entitlements(string $planName): void
    {
        $organization = $this->createOrganization($planName, true);

        $this->getJson('/api/seeker/organizations/'.$organization->slug)
            ->assertOk()
            ->assertJsonPath('data.is_verified', true)
            ->assertJsonPath('data.verified_badge_entitled', true)
            ->assertJsonPath('data.business_profile_available', true);
    }

    public function test_badge_requires_both_plan_entitlement_and_platform_verification(): void
    {
        $organization = $this->createOrganization('Starter', false);

        $this->getJson('/api/seeker/organizations/'.$organization->slug)
            ->assertOk()
            ->assertJsonPath('data.is_verified', false)
            ->assertJsonPath('data.verified_badge_entitled', true);

        $organization->currentSubscription()->first()->plan()->update([
            'features' => [
                ['name' => 'Business Profile', 'enabled' => true, 'value' => null],
                ['name' => 'Verified Badge', 'enabled' => false, 'value' => null],
            ],
        ]);

        $this->getJson('/api/seeker/organizations/'.$organization->slug)
            ->assertOk()
            ->assertJsonPath('data.is_verified', false)
            ->assertJsonPath('data.verified_badge_entitled', false)
            ->assertJsonPath('data.business_profile_available', true);
    }

    public static function tradesPlans(): array
    {
        return [['Starter'], ['Growth'], ['Elite']];
    }

    private function createOrganization(string $planName, bool $verified): Organization
    {
        $type = OrganizationType::create([
            'name' => 'Trades Professionals',
            'slug' => 'trades-professionals',
        ]);
        $plan = SubscriptionPlan::create([
            'name' => $planName,
            'plan_family' => 'trades_professional',
            'stripe_price_id' => 'test-'.$planName,
            'staff_seat_limit' => 1,
            'has_visitor_analytics' => false,
            'features' => [
                ['name' => 'Business Profile', 'enabled' => true, 'value' => null],
                ['name' => 'Verified Badge', 'enabled' => true, 'value' => null],
            ],
            'is_active' => true,
        ]);
        $organization = Organization::create([
            'name' => $planName.' Trades',
            'slug' => strtolower($planName).'-trades',
            'type_id' => $type->id,
            'plan_id' => $plan->id,
            'is_verified' => $verified,
        ]);
        Subscription::create([
            'organization_id' => $organization->id,
            'subscription_plan_id' => $plan->id,
            'stripe_subscription_id' => 'sub-'.$planName,
            'status' => 'active',
            'current_period_start' => now()->subDay(),
            'current_period_end' => now()->addDay(),
        ]);

        return $organization;
    }
}
