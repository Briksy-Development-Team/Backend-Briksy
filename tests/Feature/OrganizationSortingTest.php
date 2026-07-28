<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_organization_listing_accepts_display_id_sorting(): void
    {
        $this->seed();

        $superAdmin = User::query()->where('email', 'superadmin@brisky.example')->firstOrFail();
        Sanctum::actingAs($superAdmin, ['super_admin']);

        $response = $this->getJson('/api/super-admin/organizations?sort=display_id&direction=asc');

        $response->assertOk()
            ->assertJsonPath('success', true);
    }
}
