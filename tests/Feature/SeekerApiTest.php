<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationType;
use App\Models\PropertyListing;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SeekerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeker_can_register(): void
    {
        $response = $this->postJson('/api/v1/seeker/auth/register', [
            'name' => 'Jamie Seeker',
            'email' => 'jamie@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Seeker registered successfully.')
            ->assertJsonPath('data.email', 'jamie@example.com')
            ->assertJsonPath('data.roles.0', 'seeker');

        $this->assertDatabaseHas('users', [
            'email' => 'jamie@example.com',
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'seeker',
        ]);
    }

    public function test_property_search_returns_standard_response_shape(): void
    {
        $type = OrganizationType::create([
            'name' => 'Builder',
            'slug' => 'builder',
        ]);

        $organization = Organization::create([
            'plan_id' => null,
            'type_id' => $type->id,
            'name' => 'Harbor Homes',
            'slug' => 'harbor-homes',
            'abn' => '12345678901',
            'contact_email' => 'hello@harbor.test',
            'is_verified' => true,
        ]);

        $creator = User::create([
            'name' => 'Staff User',
            'email' => 'staff@example.com',
            'password_hash' => 'password',
            'organization_id' => $organization->id,
        ]);

        PropertyListing::create([
            'org_id' => $organization->id,
            'creator_id' => $creator->id,
            'title' => 'Sydney Harbor Apartment',
            'description' => 'Waterfront apartment.',
            'status' => 'Published',
            'suburb' => 'Sydney',
            'postcode' => '2000',
        ]);

        $response = $this->getJson('/api/v1/seeker/properties?search=Harbor');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Property listings retrieved successfully.')
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta' => [
                    'pagination' => [
                        'current_page',
                        'per_page',
                        'total',
                        'last_page',
                        'from',
                        'to',
                        'has_more_pages',
                    ],
                ],
            ]);
    }

    public function test_organization_search_filters_by_service_slug(): void
    {
        $type = OrganizationType::create([
            'name' => 'Mortgage Broker',
            'slug' => 'mortgage-broker',
        ]);

        $service = Service::create([
            'type_id' => $type->id,
            'name' => 'Refinancing',
            'slug' => 'refinancing',
        ]);

        $organization = Organization::create([
            'plan_id' => null,
            'type_id' => $type->id,
            'name' => 'Apex Lending',
            'slug' => 'apex-lending',
            'abn' => '22345678901',
            'contact_email' => 'hello@apex.test',
        ]);

        $organization->services()->attach($service->id, [
            'id' => (string) str()->uuid(),
            'description' => 'Refinancing support',
            'starting_price' => 99,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/seeker/organizations?service_slug=refinancing');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'apex-lending');
    }

    public function test_seeker_can_create_guest_inquiry(): void
    {
        $type = OrganizationType::create([
            'name' => 'Conveyancer',
            'slug' => 'conveyancer',
        ]);

        $organization = Organization::create([
            'plan_id' => null,
            'type_id' => $type->id,
            'name' => 'Clear Title Partners',
            'slug' => 'clear-title-partners',
            'abn' => '32345678901',
            'contact_email' => 'hello@clear-title.test',
        ]);

        $response = $this->postJson('/api/v1/seeker/inquiries', [
            'organization_id' => $organization->id,
            'subject' => 'Need conveyancing support',
            'message' => 'Please contact me about settlement support.',
            'seeker_name' => 'Jamie Seeker',
            'seeker_email' => 'jamie@example.com',
            'seeker_phone' => '0400000000',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'new')
            ->assertJsonPath('data.seeker.email', 'jamie@example.com');

        $this->assertDatabaseHas('inquiries', [
            'organization_id' => $organization->id,
            'seeker_email' => 'jamie@example.com',
            'status' => 'new',
        ]);
    }

    public function test_seeker_can_add_and_list_property_favorites(): void
    {
        $user = User::create([
            'name' => 'Jamie Seeker',
            'email' => 'jamie-fav@example.com',
            'password_hash' => 'secret123',
        ]);

        $type = OrganizationType::create([
            'name' => 'Builder',
            'slug' => 'builder',
        ]);

        $organization = Organization::create([
            'plan_id' => null,
            'type_id' => $type->id,
            'name' => 'Harbor Homes',
            'slug' => 'harbor-homes-fav',
            'abn' => '42345678901',
            'contact_email' => 'hello@harbor-fav.test',
        ]);

        $property = PropertyListing::create([
            'org_id' => $organization->id,
            'creator_id' => $user->id,
            'title' => 'Favorite Apartment',
            'status' => 'Published',
        ]);

        $storeResponse = $this->postJson('/api/v1/seeker/favorites', [
            'user_id' => $user->id,
            'type' => 'property',
            'target_id' => $property->id,
        ]);

        $storeResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'property')
            ->assertJsonPath('data.target.title', 'Favorite Apartment');

        $listResponse = $this->getJson('/api/v1/seeker/favorites?user_id='.$user->id.'&type=property');

        $listResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.target.title', 'Favorite Apartment');
    }

    public function test_seeker_can_leave_review_for_organization(): void
    {
        $user = User::create([
            'name' => 'Jamie Reviewer',
            'email' => 'jamie-review@example.com',
            'password_hash' => 'secret123',
        ]);

        $type = OrganizationType::create([
            'name' => 'Mortgage Broker',
            'slug' => 'mortgage-broker-review',
        ]);

        $organization = Organization::create([
            'plan_id' => null,
            'type_id' => $type->id,
            'name' => 'Apex Lending Review',
            'slug' => 'apex-lending-review',
            'abn' => '52345678901',
            'contact_email' => 'hello@apex-review.test',
        ]);

        $response = $this->postJson('/api/v1/seeker/reviews', [
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'rating' => 5,
            'comment' => 'Very responsive team.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.organization_id', $organization->id);

        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'rating' => 5,
        ]);
    }

    public function test_admin_can_register_and_receive_bearer_token(): void
    {
        $this->seed();

        $response = $this->postJson('/api/admin/auth/register', [
            'first' => 'Main',
            'last' => 'Admin',
            'email' => 'admin@example.com',
            'business_name' => 'Main Admin Co',
            'trading_name' => 'Main Admin',
            'business_type' => 'company',
            'abn_number' => '51824753556',
            'contact_phone' => '+61 400 111 222',
            'address' => '12 Example Street, Sydney NSW 2000',
            'state' => 'NSW',
            'postcode' => '2000',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Admin registered successfully.')
            ->assertJsonPath('data.user.email', 'admin@example.com')
            ->assertJsonPath('data.user.roles.0', 'admin')
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->assertDatabaseHas('roles', [
            'name' => 'admin',
        ]);
    }

    public function test_admin_or_admin_staff_can_login_with_valid_credentials(): void
    {
        $user = User::create([
            'name' => 'Team Admin',
            'email' => 'team-admin@example.com',
            'password_hash' => 'secret1234',
        ]);

        $adminRole = Role::query()->create([
            'name' => 'admin',
            'scope' => 'global',
            'is_system' => true,
        ]);

        $user->roles()->attach($adminRole->id, [
            'id' => (string) str()->uuid(),
            'organization_id' => null,
        ]);

        $response = $this->postJson('/api/admin/auth/login', [
            'email' => 'team-admin@example.com',
            'password' => 'secret1234',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonPath('data.user.roles.0', 'admin')
            ->assertJsonPath('data.token_type', 'Bearer');
    }

    public function test_only_admin_can_register_admin_staff(): void
    {
        $seeker = User::create([
            'name' => 'Regular User',
            'email' => 'regular@example.com',
            'password_hash' => 'secret1234',
        ]);

        Sanctum::actingAs($seeker, ['*']);

        $forbiddenResponse = $this->postJson('/api/admin/auth/register-staff', [
            'first' => 'Staff',
            'last' => 'One',
            'email' => 'staff1@example.com',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);

        $forbiddenResponse->assertForbidden()
            ->assertJsonPath('message', 'Only admin users can register admin staff.');

        $admin = User::create([
            'name' => 'Main Admin',
            'email' => 'main-admin@example.com',
            'password_hash' => 'secret1234',
        ]);

        $adminRole = Role::query()->firstOrCreate(
            ['name' => 'admin'],
            ['scope' => 'global', 'is_system' => true]
        );

        $admin->roles()->attach($adminRole->id, [
            'id' => (string) str()->uuid(),
            'organization_id' => null,
        ]);

        Sanctum::actingAs($admin, ['admin']);

        $successResponse = $this->postJson('/api/admin/auth/register-staff', [
            'first' => 'Staff',
            'last' => 'One',
            'email' => 'staff1@example.com',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);

        $successResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Admin staff registered successfully.')
            ->assertJsonPath('data.user.roles.0', 'admin_staff')
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->assertDatabaseHas('roles', [
            'name' => 'admin_staff',
        ]);
    }
}
