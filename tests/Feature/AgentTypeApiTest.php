<?php

namespace Tests\Feature;

use App\Models\OrganizationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentTypeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_agent_types_are_active_sorted_and_modular(): void
    {
        OrganizationType::create(['name' => 'Real Estate', 'display_name' => 'Real Estate', 'slug' => 'real-estate', 'module' => 'Real Estate', 'capability_profile' => 'real-estate-agency', 'is_active' => true, 'sort_order' => 1]);
        OrganizationType::create(['name' => 'Buyer Agents', 'display_name' => 'Buyer Agents', 'slug' => 'buyers-agent', 'module' => 'Agents', 'is_active' => true, 'sort_order' => 1]);
        OrganizationType::query()->firstOrCreate(['slug' => 'real-estate-agent'], ['name' => 'Real Estate Agents', 'display_name' => 'Real Estate Agents', 'module' => 'Agents', 'capability_profile' => 'real-estate-agent', 'is_active' => true, 'sort_order' => 2]);
        OrganizationType::create(['name' => 'Property Managers', 'display_name' => 'Property Managers', 'slug' => 'property-managers', 'module' => 'Agents', 'is_active' => false, 'sort_order' => 3]);
        OrganizationType::create(['name' => 'Builders', 'slug' => 'builders', 'module' => 'Builders', 'is_active' => true, 'sort_order' => 1]);

        $this->getJson('/api/agent-types')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'buyers-agent')
            ->assertJsonPath('data.0.module', 'Agents')
            ->assertJsonPath('data.1.slug', 'real-estate-agent')
            ->assertJsonMissing(['slug' => 'property-managers'])
            ->assertJsonMissing(['slug' => 'builders'])
            ->assertJsonMissing(['slug' => 'real-estate']);
    }

    public function test_organization_type_metadata_can_be_deactivated_without_changing_slug(): void
    {
        $type = OrganizationType::create(['name' => 'Property Managers', 'slug' => 'property-managers', 'module' => 'Agents', 'is_active' => true]);

        $type->update(['is_active' => false]);

        $this->assertDatabaseHas('organization_types', [
            'id' => $type->id,
            'slug' => 'property-managers',
            'module' => 'Agents',
            'is_active' => 0,
        ]);
    }
}
