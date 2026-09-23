<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\Organization;
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
}
