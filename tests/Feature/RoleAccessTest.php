<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_admin_and_super_admin_accounts_can_log_in(): void
    {
        $this->seed();

        $superAdminResponse = $this->postJson('/api/super-admin/auth/login', [
            'email' => 'superadmin@brisky.example',
            'password' => 'password',
        ]);

        $superAdminResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.abilities.0', 'super_admin');

        $adminResponse = $this->postJson('/api/admin/auth/login', [
            'email' => 'harborview-realty@brisky.example',
            'password' => 'password',
        ]);

        $adminResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.abilities.0', 'admin');
    }

    public function test_admin_cannot_access_super_admin_dashboard(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'harborview-realty@brisky.example')->firstOrFail();
        Sanctum::actingAs($admin, ['admin']);

        $response = $this->getJson('/api/super-admin/dashboard');

        $response->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'You are not authorized to access this resource.');
    }

    public function test_super_admin_can_access_super_admin_dashboard(): void
    {
        $this->seed();

        $superAdmin = User::query()->where('email', 'superadmin@brisky.example')->firstOrFail();
        Sanctum::actingAs($superAdmin, ['super_admin']);

        $response = $this->getJson('/api/super-admin/dashboard');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Dashboard summary retrieved successfully.');
    }
}
