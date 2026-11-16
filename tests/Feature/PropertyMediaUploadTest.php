<?php

namespace Tests\Feature;

use App\Models\PropertyListing;
use App\Models\PropertyFeature;
use App\Models\SubscriptionPlan;
use App\Support\Properties\PropertyWorkflow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PropertyMediaUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_property_with_multiple_videos(): void
    {
        $this->seed();
        Storage::fake('public');

        $admin = User::query()->where('email', 'harborview-realty@brisky.example')->firstOrFail();
        Sanctum::actingAs($admin, ['admin']);

        $response = $this->post('/api/admin/properties', $this->propertyPayload([
            UploadedFile::fake()->create('tour-one.mp4', 1024, 'video/mp4'),
            UploadedFile::fake()->create('tour-two.mp4', 1024, 'video/mp4'),
        ]));

        $response->assertCreated();
        $property = PropertyListing::query()->where('title', 'Multiple Video Property')->firstOrFail();
        $this->assertSame(2, $property->media()->where('media_type', 'video')->count());
    }

    public function test_admin_can_edit_property_with_multiple_videos(): void
    {
        $this->seed();
        Storage::fake('public');

        $admin = User::query()->where('email', 'harborview-realty@brisky.example')->firstOrFail();
        $property = PropertyListing::query()->where('org_id', $admin->organization_id)->firstOrFail();
        $existingVideoCount = $property->media()->where('media_type', 'video')->count();
        Sanctum::actingAs($admin, ['admin']);

        $response = $this->post('/api/admin/properties/' . $property->generated_id, $this->propertyPayload([
            UploadedFile::fake()->create('edit-one.mp4', 1024, 'video/mp4'),
            UploadedFile::fake()->create('edit-two.mp4', 1024, 'video/mp4'),
        ]) + ['_method' => 'PUT']);

        $response->assertOk();
        $this->assertSame($existingVideoCount + 2, $property->fresh()->media()->where('media_type', 'video')->count());
    }

    public function test_admin_media_and_amenity_edits_preserve_location_verification(): void
    {
        $this->seed();
        Storage::fake('public');

        $admin = User::query()->where('email', 'harborview-realty@brisky.example')->firstOrFail();
        $property = PropertyListing::query()->where('org_id', $admin->organization_id)->firstOrFail();
        $property->forceFill([
            'status' => PropertyWorkflow::STATUS_PUBLISHED,
            'location_verified' => true,
        ])->save();
        $featureIds = PropertyFeature::query()->limit(2)->pluck('id')->all();

        Sanctum::actingAs($admin, ['admin']);

        $this->post('/api/admin/properties/'.$property->generated_id, [
            '_method' => 'PUT',
            'title' => $property->title,
            'features' => $featureIds,
            'images' => [UploadedFile::fake()->image('updated.jpg')],
            'videos' => [UploadedFile::fake()->create('updated.mp4', 1024, 'video/mp4')],
        ])->assertOk()
            ->assertJsonPath('data.location_verified', true);

        $property->refresh();
        $this->assertTrue((bool) $property->location_verified);
        $this->assertSame(PropertyWorkflow::STATUS_PUBLISHED, $property->status);
        $this->assertCount(count($featureIds), $property->features);
    }

    public function test_media_upload_does_not_resubmit_an_approved_property(): void
    {
        $this->seed();
        Storage::fake('public');

        $admin = User::query()->where('email', 'harborview-realty@brisky.example')->firstOrFail();
        $property = PropertyListing::query()->where('org_id', $admin->organization_id)->firstOrFail();
        $existingImageCount = $property->media()->where('media_type', 'image')->count();
        $property->forceFill([
            'status' => PropertyWorkflow::STATUS_APPROVED,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now()->subDay(),
        ])->save();

        Sanctum::actingAs($admin, ['admin']);

        $this->post('/api/admin/properties/'.$property->generated_id, [
            '_method' => 'PUT',
            'title' => $property->title,
            'address' => '2 New Street, Sydney NSW 2000',
            'images' => [UploadedFile::fake()->image('marketing-update.jpg')],
            'floorplan' => UploadedFile::fake()->image('floorplan.png'),
        ])->assertOk();

        $updated = $property->fresh();
        $this->assertSame(PropertyWorkflow::STATUS_APPROVED, $updated->status);
        $this->assertSame($admin->id, $updated->reviewed_by);
        $this->assertNotNull($updated->reviewed_at);
        $this->assertSame($existingImageCount + 1, $updated->media()->where('media_type', 'image')->count());
        $this->assertSame(1, $updated->media()->where('media_type', 'floorplan')->count());
    }

    public function test_unrelated_edit_does_not_materialize_legacy_count_features(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'harborview-realty@brisky.example')->firstOrFail();
        $property = PropertyListing::query()->where('org_id', $admin->organization_id)->firstOrFail();
        $feature = PropertyFeature::query()->whereNotIn('slug', ['bedrooms', 'bathrooms', 'car_spaces'])->firstOrFail();
        $property->forceFill(['bedroom_option' => '3', 'bathroom_option' => '2', 'car_space_option' => '2'])->save();
        Sanctum::actingAs($admin, ['admin']);

        $this->putJson('/api/admin/properties/'.$property->generated_id, [
            'title' => $property->title,
            'features' => [$feature->id],
        ])->assertOk();

        $slugs = $property->fresh('features')->features->pluck('slug')->all();
        $this->assertSame([$feature->slug], $slugs);
    }

    public function test_legacy_count_features_sync_only_when_count_fields_are_submitted_and_can_be_removed(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'harborview-realty@brisky.example')->firstOrFail();
        $property = PropertyListing::query()->where('org_id', $admin->organization_id)->firstOrFail();
        $bedrooms = PropertyFeature::query()->where('slug', 'bedrooms')->firstOrFail();
        Sanctum::actingAs($admin, ['admin']);

        $this->putJson('/api/admin/properties/'.$property->generated_id, [
            'title' => $property->title,
            'bedroom_option' => '3',
        ])->assertOk();

        $this->assertSame('3', $property->fresh()->bedroom_option);
        $this->assertSame(3.0, (float) $property->fresh('features')->features->firstWhere('slug', 'bedrooms')->pivot->quantity);

        $this->putJson('/api/admin/properties/'.$property->generated_id, [
            'title' => $property->title,
            'bedroom_option' => null,
        ])->assertOk();

        $this->assertNull($property->fresh('features')->features->firstWhere('slug', 'bedrooms'));
        $this->assertNotNull(DB::table('property_listing_features')->where('property_listing_id', $property->id)->where('feature_id', $bedrooms->id)->value('deleted_at'));
    }

    public function test_real_estate_image_and_video_limits_are_independent_and_block_storage(): void
    {
        $this->seed();
        Storage::fake('public');

        $admin = User::query()->where('email', 'realestate@demo.briksy.com')->firstOrFail();
        $plan = SubscriptionPlan::query()->where('plan_family', 'property_owner')->where('name', 'Bronze')->firstOrFail();
        $features = collect($plan->features ?? [])->map(function (array $feature): array {
            if ($feature['name'] === 'Property Images') {
                $feature['enabled'] = true;
                $feature['value'] = 2;
            }
            if ($feature['name'] === 'Property Videos') {
                $feature['enabled'] = true;
                $feature['value'] = 1;
            }

            return $feature;
        })->all();
        $plan->update(['features' => $features]);
        $admin->organization()->update(['plan_id' => $plan->id]);
        $admin->organization->currentSubscription()->update(['subscription_plan_id' => $plan->id, 'status' => 'active']);
        $admin = $admin->fresh(['organization.currentSubscription.plan', 'organization.plan']);
        Sanctum::actingAs($admin, ['admin']);

        $payload = array_merge($this->propertyPayload([UploadedFile::fake()->create('tour.mp4', 1024, 'video/mp4')]), [
            'title' => 'Independent Media Limits',
            'images' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.jpg'),
            ],
        ]);
        $createResponse = $this->post('/api/admin/properties', $payload);
        $createResponse->assertCreated();
        $property = PropertyListing::query()->where('title', 'Independent Media Limits')->firstOrFail();

        $this->post('/api/admin/properties/'.$property->generated_id, [
            '_method' => 'PUT',
            'title' => $property->title,
            'images' => [UploadedFile::fake()->image('three.jpg')],
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'PLAN_IMAGE_LIMIT_REACHED');

        $this->post('/api/admin/properties/'.$property->generated_id, [
            '_method' => 'PUT',
            'title' => $property->title,
            'videos' => [UploadedFile::fake()->create('tour-two.mp4', 1024, 'video/mp4')],
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'PLAN_VIDEO_LIMIT_REACHED');

        $this->assertSame(2, $property->fresh()->media()->where('media_type', 'image')->count());
        $this->assertSame(1, $property->fresh()->media()->where('media_type', 'video')->count());
    }

    public function test_super_admin_media_upload_is_not_blocked_by_property_owner_plan(): void
    {
        $this->seed();
        Storage::fake('public');

        $superAdmin = User::query()->where('email', 'superadmin@brisky.example')->firstOrFail();
        $property = PropertyListing::query()->firstOrFail();
        $existingImageCount = $property->media()->where('media_type', 'image')->count();
        Sanctum::actingAs($superAdmin, ['super_admin']);

        $this->post('/api/super-admin/properties/'.$property->generated_id, [
            '_method' => 'PUT',
            'title' => $property->title,
            'images' => [UploadedFile::fake()->image('super-admin-upload.jpg')],
        ])->assertOk();

        $this->assertSame($existingImageCount + 1, $property->fresh()->media()->where('media_type', 'image')->count());
    }

    public function test_property_without_active_images_accepts_replacement_upload_when_image_limit_is_zero(): void
    {
        $this->seed();
        Storage::fake('public');

        $admin = User::query()->where('email', 'realestate@demo.briksy.com')->firstOrFail();
        $plan = SubscriptionPlan::query()->where('plan_family', 'property_owner')->where('name', 'Bronze')->firstOrFail();
        $features = collect($plan->features ?? [])->map(function (array $feature): array {
            if ($feature['name'] === 'Property Images') {
                $feature['enabled'] = true;
                $feature['value'] = 0;
            }

            return $feature;
        })->all();
        $plan->update(['features' => $features]);
        $admin->organization()->update(['plan_id' => $plan->id]);
        $admin->organization->currentSubscription()->update(['subscription_plan_id' => $plan->id, 'status' => 'active']);
        Sanctum::actingAs($admin, ['admin']);

        $property = $this->post('/api/admin/properties', [
            'title' => 'Replacement Image Property',
            'address' => '1 Test Street, Sydney NSW 2000',
            'address_line_1' => '1 Test Street',
            'full_address' => '1 Test Street, Sydney NSW 2000',
            'suburb' => 'Sydney',
            'state' => 'NSW',
            'postcode' => '2000',
            'country' => 'Australia',
        ])->assertCreated()->json('data');

        $listing = PropertyListing::query()->findOrFail($property['id']);
        $this->post('/api/admin/properties/'.$listing->generated_id, [
            '_method' => 'PUT',
            'title' => $listing->title,
            'images' => [UploadedFile::fake()->image('replacement.jpg')],
        ])->assertOk();

        $this->assertSame(1, $listing->fresh()->media()->where('media_type', 'image')->count());
    }

    private function propertyPayload(array $videos): array
    {
        return [
            'title' => 'Multiple Video Property',
            'address' => '1 Test Street, Sydney NSW 2000',
            'address_line_1' => '1 Test Street',
            'full_address' => '1 Test Street, Sydney NSW 2000',
            'suburb' => 'Sydney',
            'state' => 'NSW',
            'postcode' => '2000',
            'country' => 'Australia',
            'videos' => $videos,
        ];
    }
}
