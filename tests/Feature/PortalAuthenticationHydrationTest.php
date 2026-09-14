<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PortalAuthenticationHydrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_super_admin_auth_me_routes_accept_portal_tokens(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'harborview-realty@brisky.example')->firstOrFail();
        Sanctum::actingAs($admin, ['admin']);

        $this->getJson('/api/admin/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $admin->id);

        $superAdmin = User::query()->where('email', 'superadmin@brisky.example')->firstOrFail();
        Sanctum::actingAs($superAdmin, ['super_admin']);

        $this->getJson('/api/super-admin/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $superAdmin->id);
    }

    public function test_portal_logout_routes_accept_portal_tokens(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'harborview-realty@brisky.example')->firstOrFail();
        Sanctum::actingAs($admin, ['admin']);

        $this->postJson('/api/admin/auth/logout')->assertOk();

        $superAdmin = User::query()->where('email', 'superadmin@brisky.example')->firstOrFail();
        Sanctum::actingAs($superAdmin, ['super_admin']);

        $this->postJson('/api/super-admin/auth/logout')->assertOk();
    }
}
