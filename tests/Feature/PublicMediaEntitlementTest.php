<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationType;
use App\Models\Service;
use App\Models\ServiceMedia;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicMediaEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_service_detail_returns_only_entitled_media(): void
    {
        $type = OrganizationType::create(['name' => 'Trades', 'slug' => 'trades-professionals']);
        $plan = SubscriptionPlan::create([
            'name' => 'Media test',
            'plan_family' => 'trades_professional',
            'stripe_price_id' => 'media-test-price',
            'staff_seat_limit' => 1,
            'features' => [
                ['name' => 'Portfolio Photos', 'enabled' => true, 'value' => 2],
                ['name' => 'Portfolio Videos', 'enabled' => true, 'value' => 1],
            ],
            'is_active' => true,
        ]);
        $organization = Organization::create([
            'name' => 'Verified Trades',
            'slug' => 'verified-trades',
            'type_id' => $type->id,
            'plan_id' => $plan->id,
            'is_verified' => true,
        ]);
        Subscription::create([
            'organization_id' => $organization->id,
            'subscription_plan_id' => $plan->id,
            'stripe_subscription_id' => 'sub-media-test',
            'status' => 'active',
            'current_period_start' => now()->subDay(),
            'current_period_end' => now()->addDay(),
        ]);
        $service = Service::create([
            'type_id' => $type->id,
            'organization_id' => $organization->id,
            'name' => 'Electrical inspection',
            'slug' => 'electrical-inspection',
            'is_active' => true,
        ]);

        foreach (range(1, 4) as $order) {
            ServiceMedia::create([
                'service_id' => $service->id,
                'file_url' => "images/{$order}.jpg",
                'media_type' => 'image',
                'sort_order' => $order,
            ]);
        }
        foreach (range(1, 3) as $order) {
            ServiceMedia::create([
                'service_id' => $service->id,
                'file_url' => "videos/{$order}.mp4",
                'media_type' => 'video',
                'sort_order' => $order,
            ]);
        }

        $response = $this->getJson("/api/seeker/services/{$service->generated_id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data.images')
            ->assertJsonCount(1, 'data.videos')
            ->assertJsonPath('data.images.0.sort_order', 1)
            ->assertJsonPath('data.videos.0.sort_order', 1);
    }
}
