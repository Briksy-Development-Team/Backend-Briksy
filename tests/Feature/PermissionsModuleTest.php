<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Organization;
use App\Models\OrganizationType;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PermissionsModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_has_all_permissions(): void
    {
        $this->seed();

        $superAdmin = User::query()->where('email', 'superadmin@brisky.example')->firstOrFail();
        Sanctum::actingAs($superAdmin, ['super_admin']);

        $response = $this->getJson('/api/me/permissions');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $effective = $response->json('data.effective_permission_names');

        $this->assertCount(Permission::query()->count(), $effective);
        $this->assertContains('permission.manage', $effective);
    }

    public function test_admin_has_default_organization_permissions(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'harborview-realty@brisky.example')->firstOrFail();
        Sanctum::actingAs($admin, ['admin']);

        $response = $this->getJson('/api/me/permissions');

        $response->assertOk();

        $effective = $response->json('data.effective_permission_names');

        $this->assertContains('property.view', $effective);
        $this->assertContains('settings.update', $effective);
        $this->assertNotContains('permission.manage', $effective);
    }

    public function test_user_explicit_deny_overrides_role_allow(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'harborview-realty@brisky.example')->firstOrFail();
        $permissionId = Permission::query()->where('name', 'property.view')->value('id');

        UserPermission::query()->updateOrCreate(
            ['user_id' => $admin->id, 'permission_id' => $permissionId],
            ['effect' => 'deny']
        );

        Sanctum::actingAs($admin, ['admin']);

        $this->getJson('/api/admin/properties')->assertForbidden();

        $response = $this->getJson('/api/me/permissions');
        $response->assertOk();
        $this->assertNotContains('property.view', $response->json('data.effective_permission_names'));
    }

    public function test_user_explicit_allow_grants_extra_permission(): void
    {
        $this->seed();

        $viewerRole = Role::query()->where('name', 'viewer')->firstOrFail();
        $organizationType = OrganizationType::query()->where('slug', 'property-management')->firstOrFail();
        $organization = Organization::create([
            'name' => 'Viewer Business',
            'trading_name' => null,
            'contact_email' => 'viewer-business@example.com',
            'contact_phone' => null,
            'abn' => '51824753556',
            'business_type' => 'company',
            'business_verification_status' => 'verified',
            'address' => '1 Test Street',
            'state' => 'NSW',
            'postcode' => '2000',
            'plan_id' => null,
            'type_id' => $organizationType->id,
            'ranking_priority' => 1,
            'avg_org_rating' => 0,
            'slug' => 'viewer-business',
            'stripe_customer_id' => null,
            'is_verified' => true,
            'abn_verified' => true,
            'abn_verified_at' => now(),
            'entity_name' => 'Viewer Business',
            'entity_type' => 'Australian Private Company',
            'entity_status' => 'Active',
            'gst_registered' => true,
            'abn_effective_from' => now()->toDateString(),
            'trial_started_at' => now(),
            'trial_ends_at' => now()->addDays(15),
            'subscription_status' => 'trialing',
        ]);
        $user = User::query()->create([
            'name' => 'Viewer User',
            'email' => 'viewer-test@example.com',
            'password_hash' => 'password',
            'organization_id' => $organization->id,
            'id_verified' => false,
        ]);

        $user->roles()->syncWithoutDetaching([
            $viewerRole->id => [
                'id' => (string) str()->uuid(),
                'organization_id' => null,
            ],
        ]);

        $permissionId = Permission::query()->where('name', 'order.view')->value('id');
        UserPermission::query()->create([
            'user_id' => $user->id,
            'permission_id' => $permissionId,
            'effect' => 'allow',
        ]);

        Sanctum::actingAs($user, ['admin']);

        $response = $this->getJson('/api/me/permissions');

        $response->assertOk();
        $this->assertContains('order.view', $response->json('data.effective_permission_names'));
    }

    public function test_permission_middleware_supports_pipe_or_permissions(): void
    {
        $this->seed();

        Route::middleware(['auth:sanctum', 'permission:missing.permission|dashboard.view'])
            ->get('/api/test/pipe-permission', fn () => response()->json(['ok' => true]));

        $admin = User::query()->where('email', 'harborview-realty@brisky.example')->firstOrFail();
        Sanctum::actingAs($admin, ['admin']);

        $this->getJson('/api/test/pipe-permission')->assertOk()->assertJsonPath('ok', true);
    }

    public function test_super_admin_can_sync_role_permissions(): void
    {
        $this->seed();

        $superAdmin = User::query()->where('email', 'superadmin@brisky.example')->firstOrFail();
        Sanctum::actingAs($superAdmin, ['super_admin']);

        $role = Role::query()->where('name', 'viewer')->firstOrFail();
        $permissionIds = Permission::query()->whereIn('name', ['dashboard.view', 'property.view'])->pluck('id')->all();

        $response = $this->putJson("/api/super-admin/permissions/roles/{$role->id}/permissions", [
            'permission_ids' => $permissionIds,
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $role->id,
            'permission_id' => $permissionIds[0],
        ]);
    }

    public function test_admin_cannot_sync_super_admin_permissions(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'harborview-realty@brisky.example')->firstOrFail();
        Sanctum::actingAs($admin, ['admin']);

        $role = Role::query()->where('name', 'super_admin')->firstOrFail();

        $this->putJson("/api/super-admin/permissions/roles/{$role->id}/permissions", [
            'permission_ids' => [],
        ])->assertForbidden();
    }
}
