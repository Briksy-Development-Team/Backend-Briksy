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
}
