<?php

namespace Tests\Feature;

use App\Models\PropertyListing;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PropertyOfferTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_estate_admin_cannot_exceed_the_promo_offer_limit(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'realestate@demo.briksy.com')->firstOrFail();
        $property = PropertyListing::query()->where('org_id', $admin->organization_id)->firstOrFail();
        $plan = SubscriptionPlan::query()
            ->where('plan_family', 'property_owner')
            ->where('name', 'Bronze')
            ->firstOrFail();

        $features = collect($plan->features ?? [])->map(function (array $feature): array {
            if (($feature['name'] ?? null) === 'Promo Offers') {
                $feature['enabled'] = true;
                $feature['value'] = 2;
            }

            return $feature;
        })->all();
        $plan->update(['features' => $features]);
        $admin->organization()->update(['plan_id' => $plan->id]);
        $admin->organization->currentSubscription()->update([
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
        ]);

        Sanctum::actingAs($admin, ['admin']);

        $payload = fn (string $title): array => [
            'property_listing_id' => $property->id,
            'title' => $title,
            'is_active' => true,
        ];

        $this->postJson('/api/admin/property-offers', $payload('Offer one'))->assertCreated();
        $this->postJson('/api/admin/property-offers', $payload('Offer two'))->assertCreated();
        $this->postJson('/api/admin/property-offers', $payload('Offer three'))
            ->assertStatus(422)
            ->assertJsonPath('code', 'PLAN_PROMO_OFFERS_LIMIT_REACHED');

        $this->assertDatabaseCount('property_offers', 2);
    }
}
