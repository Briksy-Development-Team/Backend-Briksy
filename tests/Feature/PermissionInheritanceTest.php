<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PermissionInheritanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_creation_copies_role_defaults(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'harborview-realty@brisky.example')->firstOrFail();
        Sanctum::actingAs($admin, ['admin']);

        $response = $this->postJson('/api/admin/staff', [
            'name' => 'Inherited Staff Member',
            'email' => 'inherited-staff@example.com',
            'password' => 'password',
        ]);

        $response->assertCreated();
        $staff = User::query()->where('email', 'inherited-staff@example.com')->firstOrFail();
        $defaults = Role::query()->where('name', 'admin_staff')->firstOrFail()->permissions->pluck('name');

        $this->assertSame(
            $defaults->sort()->values()->all(),
            $staff->directPermissions()->wherePivot('effect', 'allow')->get()->pluck('name')->sort()->values()->all()
        );
    }

    public function test_unchecked_role_default_is_persisted_as_user_deny(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'harborview-realty@brisky.example')->firstOrFail();
        Sanctum::actingAs($admin, ['admin']);

        $staff = $this->postJson('/api/admin/staff', [
            'name' => 'Customized Staff Member',
            'email' => 'customized-staff@example.com',
            'password' => 'password',
            'permissions' => ['dashboard.view', 'user.view'],
        ])->assertCreated()->json('data');

        $propertyPermissionId = Permission::query()->where('name', 'property.view')->value('id');
        $this->assertDatabaseHas('user_permissions', [
            'user_id' => $staff['id'],
            'permission_id' => $propertyPermissionId,
            'effect' => 'deny',
        ]);

        Sanctum::actingAs(User::query()->findOrFail($staff['id']), ['admin_staff']);
        $this->getJson('/api/admin/properties')->assertForbidden();
    }

    public function test_new_property_staff_can_login_and_reach_property_management(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'harborview-realty@brisky.example')->firstOrFail();
        Sanctum::actingAs($admin, ['admin']);

        $this->postJson('/api/admin/staff', [
            'name' => 'Property Portal Staff',
            'email' => 'property-portal-staff@example.com',
            'password' => 'password',
        ])->assertCreated();

        $login = $this->postJson('/api/admin/auth/login', [
            'email' => 'property-portal-staff@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->assertSame(['admin_staff'], $login->json('data.abilities'));
        Sanctum::actingAs(User::query()->where('email', 'property-portal-staff@example.com')->firstOrFail(), ['admin_staff']);
        $this->getJson('/api/admin/properties')->assertOk();
    }

    public function test_seeding_does_not_overwrite_role_defaults_or_user_overrides(): void
    {
        $this->seed();
        $role = Role::query()->where('name', 'admin_staff')->firstOrFail();
        $permissionId = Permission::query()->where('name', 'dashboard.view')->value('id');
        $role->permissions()->detach($permissionId);

        $staff = User::query()->where('email', 'team+harborview-realty@brisky.example')->firstOrFail();
        $staff->directPermissions()->syncWithoutDetaching([
            $permissionId => ['id' => (string) str()->uuid(), 'effect' => 'deny'],
        ]);

        $this->seed();

        $this->assertDatabaseMissing('role_permissions', [
            'role_id' => $role->id,
            'permission_id' => $permissionId,
        ]);
        $this->assertDatabaseHas('user_permissions', [
            'user_id' => $staff->id,
            'permission_id' => $permissionId,
            'effect' => 'deny',
        ]);
    }
}
