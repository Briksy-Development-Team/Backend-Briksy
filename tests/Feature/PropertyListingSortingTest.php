<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PropertyListingSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_property_listing_accepts_display_id_sorting(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'harborview-realty@brisky.example')->firstOrFail();
        Sanctum::actingAs($admin, ['admin']);

        $response = $this->getJson('/api/admin/properties?sort=display_id&direction=asc');

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_super_admin_property_listing_accepts_display_id_sorting(): void
    {
        $this->seed();

        $superAdmin = User::query()->where('email', 'superadmin@brisky.example')->firstOrFail();
        Sanctum::actingAs($superAdmin, ['super_admin']);

        $response = $this->getJson('/api/super-admin/properties?sort=display_id&direction=asc');

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_property_listing_filters_by_organization_slug(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'harborview-realty@brisky.example')->firstOrFail();
        Sanctum::actingAs($admin, ['admin']);

        $response = $this->getJson('/api/admin/properties?filter[organization_slug]=harborview-realty');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $items = $response->json('data');
        $this->assertNotEmpty($items);
        $this->assertTrue(collect($items)->every(fn (array $item) => $item['organization']['slug'] === 'harborview-realty'));
    }

    public function test_super_admin_property_listing_filters_by_organization_slug(): void
    {
        $this->seed();

        $superAdmin = User::query()->where('email', 'superadmin@brisky.example')->firstOrFail();
        Sanctum::actingAs($superAdmin, ['super_admin']);

        $response = $this->getJson('/api/super-admin/properties?filter[organization_slug]=harborview-realty');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $items = $response->json('data');
        $this->assertNotEmpty($items);
        $this->assertTrue(collect($items)->every(fn (array $item) => $item['organization']['slug'] === 'harborview-realty'));
    }
}
