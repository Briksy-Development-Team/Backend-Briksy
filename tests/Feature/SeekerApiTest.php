<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationType;
use App\Models\PropertyListing;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
