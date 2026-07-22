<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Organization;
use App\Models\OrganizationType;
use App\Models\PropertyListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicMediaRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_serves_property_listing_media_through_a_public_route(): void
    {
        Storage::fake('public');

        $organizationType = OrganizationType::query()->create([
            'name' => 'Company',
            'slug' => 'company',
        ]);

        $organization = Organization::query()->create([
            'type_id' => $organizationType->id,
            'name' => 'Test Org',
            'slug' => 'test-org',
            'abn' => '12345678901',
            'is_verified' => true,
        ]);

        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password_hash' => 'password123',
            'organization_id' => $organization->id,
        ]);

        $property = PropertyListing::query()->create([
            'org_id' => $organization->id,
            'creator_id' => $user->id,
            'title' => 'Bondi Beach',
            'status' => 'Draft',
            'latitude' => -33.8948977,
            'longitude' => 151.2735455,
            'suburb' => 'Bondi',
            'postcode' => '2026',
        ]);

        $storagePath = "property-listings/{$property->id}/images/sample.jpg";
        Storage::disk('public')->put($storagePath, 'sample-image-content');

        $media = Media::query()->create([
            'property_listing_id' => $property->id,
            'file_url' => url("/storage/{$storagePath}"),
            'media_type' => 'image',
            'is_primary' => true,
            'sort_order' => 1,
        ]);

        $response = $this->get("/api/media/{$media->id}");

        $response->assertOk();
        $this->assertStringContainsString('max-age=86400', (string) $response->headers->get('cache-control'));
        Storage::disk('public')->assertExists($storagePath);
    }

    public function test_it_deletes_property_listing_media_for_the_listing_owner(): void
    {
        Storage::fake('public');

        $organizationType = OrganizationType::query()->create([
            'name' => 'Company',
            'slug' => 'company',
        ]);

        $organization = Organization::query()->create([
            'type_id' => $organizationType->id,
            'name' => 'Test Org',
            'slug' => 'test-org',
            'abn' => '12345678901',
            'is_verified' => true,
        ]);

        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password_hash' => 'password123',
            'organization_id' => $organization->id,
        ]);

        $property = PropertyListing::query()->create([
            'org_id' => $organization->id,
            'creator_id' => $user->id,
            'title' => 'Bondi Beach',
            'status' => 'Draft',
            'latitude' => -33.8948977,
            'longitude' => 151.2735455,
            'suburb' => 'Bondi',
            'postcode' => '2026',
        ]);

        $storagePath = "property-listings/{$property->id}/images/sample.jpg";
        Storage::disk('public')->put($storagePath, 'sample-image-content');

        $media = Media::query()->create([
            'property_listing_id' => $property->id,
            'file_url' => url("/storage/{$storagePath}"),
            'media_type' => 'image',
            'is_primary' => true,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user, 'sanctum')->deleteJson("/api/media/{$media->id}");

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        Storage::disk('public')->assertMissing($storagePath);
        $this->assertSoftDeleted('property_listing_media', [
            'id' => $media->id,
        ]);
    }
}
