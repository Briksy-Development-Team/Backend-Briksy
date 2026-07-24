<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationType;
use App\Models\Media;
use App\Models\PropertyListing;
use App\Models\PropertyType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PropertyMapEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_database_returns_empty_map_payload(): void
    {
        $user = $this->makeSuperAdmin();
        Sanctum::actingAs($user, ['super_admin']);

        $response = $this->getJson('/api/v1/super-admin/property-map');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertExactJson([
                'success' => true,
                'message' => 'Property map data retrieved successfully.',
                'data' => [],
            ]);
    }

    public function test_map_endpoint_returns_only_properties_with_valid_coordinates(): void
    {
        $user = $this->makeSuperAdmin();
        Sanctum::actingAs($user, ['super_admin']);

        $organization = Organization::query()->create([
            'type_id' => OrganizationType::query()->create([
                'name' => 'Real Estate',
                'slug' => 'real-estate',
            ])->id,
            'name' => 'Map Test Realty',
            'slug' => 'map-test-realty',
            'business_type' => 'company',
            'business_verification_status' => 'verified',
            'is_verified' => true,
            'contact_email' => 'hello@maptest.example',
            'contact_phone' => '+61 400 000 000',
            'abn' => '51824753556',
            'state' => 'Victoria',
            'postcode' => '3000',
        ]);

        $propertyType = PropertyType::query()->create([
            'name' => 'Apartment',
            'slug' => 'apartment',
        ]);

        PropertyListing::query()->create([
            'id' => (string) Str::uuid(),
            'org_id' => $organization->id,
            'creator_id' => $user->id,
            'generated_id' => 'PROP-000101',
            'property_type_id' => $propertyType->id,
            'title' => 'Luxury Apartment',
            'latitude' => -37.812,
            'longitude' => 144.962,
            'status' => 'Approved',
            'location_verified' => true,
            'suburb' => 'Melbourne',
            'state' => 'Victoria',
            'country' => 'Australia',
            'address' => '10 Collins Street',
            'formatted_address' => '10 Collins Street, Melbourne VIC, Australia',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        PropertyListing::query()->create([
            'id' => (string) Str::uuid(),
            'org_id' => $organization->id,
            'creator_id' => $user->id,
            'generated_id' => 'PROP-000102',
            'property_type_id' => $propertyType->id,
            'title' => 'Missing Coordinates',
            'latitude' => null,
            'longitude' => null,
            'status' => 'Approved',
            'location_verified' => false,
            'suburb' => 'Melbourne',
            'state' => 'Victoria',
            'country' => 'Australia',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $property = PropertyListing::query()->where('generated_id', 'PROP-000101')->firstOrFail();

        Media::query()->create([
            'id' => (string) Str::uuid(),
            'property_listing_id' => $property->id,
            'file_url' => '/storage/property-listings/' . $property->id . '/images/cover-1.png',
            'media_type' => 'image',
            'is_primary' => true,
            'sort_order' => 1,
        ]);

        Media::query()->create([
            'id' => (string) Str::uuid(),
            'property_listing_id' => $property->id,
            'file_url' => 'https://samplelib.com/lib/preview/mp4/sample-5s.mp4',
            'media_type' => 'video',
            'is_primary' => false,
            'sort_order' => 2,
        ]);

        $response = $this->getJson('/api/v1/super-admin/property-map?search=Luxury');

        $response->assertOk()
            ->assertJsonPath('data.0.property_number', 'PROP-000101')
            ->assertJsonPath('data.0.city', 'Melbourne')
            ->assertJsonPath('data.0.verified', true)
            ->assertJsonPath('data.0.images.0.is_primary', true)
            ->assertJsonPath('data.0.videos.0.url', 'https://samplelib.com/lib/preview/mp4/sample-5s.mp4')
            ->assertJsonMissingPath('data.1');
    }

    public function test_super_admin_employee_can_access_property_map(): void
    {
        $user = $this->makeRoleUser('super_admin_employee', 'super-admin-staff@example.test');
        Sanctum::actingAs($user, ['super_admin_employee']);

        $this->getJson('/api/v1/super-admin/property-map')->assertOk();
    }

    public function test_admin_cannot_access_property_map(): void
    {
        $user = User::query()->create([
            'name' => 'Admin User',
            'email' => 'admin-user@example.test',
            'password_hash' => 'password',
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user, ['admin']);

        $this->getJson('/api/v1/super-admin/property-map')->assertForbidden();
    }

    private function makeSuperAdmin(): User
    {
        return $this->makeRoleUser('super_admin', 'super-admin@example.test');
    }

    private function makeRoleUser(string $roleName, string $email): User
    {
        $user = User::query()->create([
            'name' => Str::headline(str_replace(['@', '.test'], '', $email)),
            'email' => $email,
            'password_hash' => 'password',
            'email_verified_at' => now(),
        ]);

        $role = Role::query()->firstOrCreate(
            ['name' => $roleName],
            ['scope' => 'global', 'is_system' => true]
        );

        $user->roles()->attach($role->id, [
            'id' => (string) Str::uuid(),
            'organization_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }
}
