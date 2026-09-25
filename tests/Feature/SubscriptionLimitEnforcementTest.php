<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\Organization;
use App\Models\PropertyListing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionLimitEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_seat_limit_cannot_be_bypassed_through_direct_api_request(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'trades@demo.briksy.com')->firstOrFail();
        $starter = SubscriptionPlan::query()
            ->where('plan_family', 'trades_professional')
            ->where('name', 'Starter')
            ->firstOrFail();
        Organization::query()->whereKey($admin->organization_id)->update(['plan_id' => $starter->id]);
        $admin->organization->currentSubscription()->update(['subscription_plan_id' => $starter->id, 'status' => 'active']);
        $admin->unsetRelation('organization');
        $admin->load('organization.currentSubscription.plan');
        Sanctum::actingAs($admin, ['admin']);

        $this->postJson('/api/admin/auth/register-staff', [
            'first' => 'Limit',
            'last' => 'Bypass',
            'email' => 'limit-bypass@example.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'PLAN_STAFF_LIMIT_REACHED');
    }

    public function test_property_listing_limit_cannot_be_bypassed_through_direct_api_request(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'realestate@demo.briksy.com')->firstOrFail();
        $plan = SubscriptionPlan::query()
            ->where('plan_family', 'property_owner')
            ->where('name', 'Bronze')
            ->firstOrFail();
        $features = collect($plan->features ?? [])->map(function (array $feature): array {
            if ($feature['name'] === 'Property Listings') {
                $feature['enabled'] = true;
                $feature['value'] = 0;
            }

            return $feature;
        })->all();

        $plan->update([
            'features' => $features,
            'property_limit' => 0,
        ]);
        Organization::query()->whereKey($admin->organization_id)->update(['plan_id' => $plan->id]);
        $admin->organization->currentSubscription()->update(['subscription_plan_id' => $plan->id, 'status' => 'active']);
        $admin->unsetRelation('organization');
        $admin->load('organization.currentSubscription.plan', 'organization.plan');
        Sanctum::actingAs($admin, ['admin']);

        $beforeCount = PropertyListing::query()->where('org_id', $admin->organization_id)->count();

        $this->postJson('/api/admin/properties', [
            'title' => 'Limit Bypass Property',
            'address' => '1 Test Street, Sydney NSW 2000',
            'address_line_1' => '1 Test Street',
            'full_address' => '1 Test Street, Sydney NSW 2000',
            'suburb' => 'Sydney',
            'state' => 'NSW',
            'postcode' => '2000',
            'country' => 'Australia',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'PLAN_PROPERTY_LIMIT_REACHED');

        $this->assertSame($beforeCount, PropertyListing::query()->where('org_id', $admin->organization_id)->count());
    }
}
