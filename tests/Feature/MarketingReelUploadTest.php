<?php

namespace Tests\Feature;

use App\Models\MarketingReel;
use App\Models\PropertyListing;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketingReelUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_marketing_reels_use_the_configured_plan_limit(): void
    {
        $this->seed();
        Storage::fake('public');

        $admin = User::query()->where('email', 'realestate@demo.briksy.com')->firstOrFail();
        $property = PropertyListing::query()->where('org_id', $admin->organization_id)->firstOrFail();
        $plan = SubscriptionPlan::query()->where('plan_family', 'property_owner')->where('name', 'Bronze')->firstOrFail();
        $features = collect($plan->features ?? [])->map(function (array $feature): array {
            if (($feature['name'] ?? '') === 'Reels') {
                $feature['enabled'] = true;
                $feature['value'] = 1;
            }
            return $feature;
        })->all();
        $plan->update(['features' => $features]);
        $admin->organization()->update(['plan_id' => $plan->id]);
        $admin->organization->currentSubscription()->update(['subscription_plan_id' => $plan->id, 'status' => 'active']);
        Sanctum::actingAs($admin, ['admin']);

        $this->post('/api/admin/marketing-reels/property/'.$property->id, [
            'reels' => [UploadedFile::fake()->create('first.mp4', 100, 'video/mp4')],
        ])->assertCreated();

        $this->post('/api/admin/marketing-reels/property/'.$property->id, [
            'reels' => [UploadedFile::fake()->create('second.mp4', 100, 'video/mp4')],
        ])->assertStatus(422)->assertJsonPath('code', 'PLAN_REELS_LIMIT_REACHED');

        $this->assertSame(1, MarketingReel::query()->where('model_id', $property->id)->count());
    }

    public function test_another_organization_cannot_manage_a_property_reel(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'realestate@demo.briksy.com')->firstOrFail();
        $other = User::query()->where('email', 'trades@demo.briksy.com')->firstOrFail();
        $property = PropertyListing::query()->where('org_id', $admin->organization_id)->firstOrFail();
        Sanctum::actingAs($other, ['admin']);

        $this->post('/api/admin/marketing-reels/property/'.$property->id, [
            'reels' => [UploadedFile::fake()->create('blocked.mp4', 100, 'video/mp4')],
        ])->assertForbidden();
    }
}
