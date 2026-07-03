<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            ['module' => 'dashboard', 'action' => 'view', 'name' => 'dashboard.view', 'display_name' => 'View Dashboard'],
            ['module' => 'company', 'action' => 'view', 'name' => 'company.view', 'display_name' => 'View Companies'],
            ['module' => 'company', 'action' => 'create', 'name' => 'company.create', 'display_name' => 'Create Companies'],
            ['module' => 'company', 'action' => 'update', 'name' => 'company.update', 'display_name' => 'Update Companies'],
            ['module' => 'company', 'action' => 'delete', 'name' => 'company.delete', 'display_name' => 'Delete Companies'],
            ['module' => 'plan', 'action' => 'view', 'name' => 'plan.view', 'display_name' => 'View Plans'],
            ['module' => 'plan', 'action' => 'create', 'name' => 'plan.create', 'display_name' => 'Create Plans'],
            ['module' => 'plan', 'action' => 'update', 'name' => 'plan.update', 'display_name' => 'Update Plans'],
            ['module' => 'plan', 'action' => 'delete', 'name' => 'plan.delete', 'display_name' => 'Delete Plans'],
            ['module' => 'addon', 'action' => 'view', 'name' => 'addon.view', 'display_name' => 'View Add-ons'],
            ['module' => 'addon', 'action' => 'create', 'name' => 'addon.create', 'display_name' => 'Create Add-ons'],
            ['module' => 'addon', 'action' => 'update', 'name' => 'addon.update', 'display_name' => 'Update Add-ons'],
            ['module' => 'addon', 'action' => 'delete', 'name' => 'addon.delete', 'display_name' => 'Delete Add-ons'],
            ['module' => 'billing', 'action' => 'view', 'name' => 'billing.view', 'display_name' => 'View Billing'],
            ['module' => 'billing', 'action' => 'manage', 'name' => 'billing.manage', 'display_name' => 'Manage Billing'],
            ['module' => 'subscription', 'action' => 'view', 'name' => 'subscription.view', 'display_name' => 'View Subscriptions'],
            ['module' => 'dynamic_id', 'action' => 'view', 'name' => 'dynamic_id.view', 'display_name' => 'View Dynamic ID Settings'],
            ['module' => 'dynamic_id', 'action' => 'manage', 'name' => 'dynamic_id.manage', 'display_name' => 'Manage Dynamic ID Settings'],
            ['module' => 'plan_request', 'action' => 'view', 'name' => 'plan_request.view', 'display_name' => 'View Plan Requests'],
            ['module' => 'plan_request', 'action' => 'create', 'name' => 'plan_request.create', 'display_name' => 'Create Plan Requests'],
            ['module' => 'plan_request', 'action' => 'approve', 'name' => 'plan_request.approve', 'display_name' => 'Approve Plan Requests'],
            ['module' => 'plan_request', 'action' => 'reject', 'name' => 'plan_request.reject', 'display_name' => 'Reject Plan Requests'],
            ['module' => 'plan_request', 'action' => 'update', 'name' => 'plan_request.update', 'display_name' => 'Update Plan Requests'],
            ['module' => 'plan_request', 'action' => 'delete', 'name' => 'plan_request.delete', 'display_name' => 'Delete Plan Requests'],
            ['module' => 'referral', 'action' => 'view', 'name' => 'referral.view', 'display_name' => 'View Referral Programs'],
            ['module' => 'referral', 'action' => 'create', 'name' => 'referral.create', 'display_name' => 'Create Referral Programs'],
            ['module' => 'referral', 'action' => 'update', 'name' => 'referral.update', 'display_name' => 'Update Referral Programs'],
            ['module' => 'referral', 'action' => 'delete', 'name' => 'referral.delete', 'display_name' => 'Delete Referral Programs'],
            ['module' => 'coupon', 'action' => 'view', 'name' => 'coupon.view', 'display_name' => 'View Coupons'],
            ['module' => 'coupon', 'action' => 'create', 'name' => 'coupon.create', 'display_name' => 'Create Coupons'],
            ['module' => 'coupon', 'action' => 'update', 'name' => 'coupon.update', 'display_name' => 'Update Coupons'],
            ['module' => 'coupon', 'action' => 'delete', 'name' => 'coupon.delete', 'display_name' => 'Delete Coupons'],
            ['module' => 'order', 'action' => 'view', 'name' => 'order.view', 'display_name' => 'View Orders'],
            ['module' => 'order', 'action' => 'create', 'name' => 'order.create', 'display_name' => 'Create Orders'],
            ['module' => 'order', 'action' => 'update', 'name' => 'order.update', 'display_name' => 'Update Orders'],
            ['module' => 'order', 'action' => 'cancel', 'name' => 'order.cancel', 'display_name' => 'Cancel Orders'],
            ['module' => 'order', 'action' => 'delete', 'name' => 'order.delete', 'display_name' => 'Delete Orders'],
            ['module' => 'email_template', 'action' => 'view', 'name' => 'email_template.view', 'display_name' => 'View Email Templates'],
            ['module' => 'email_template', 'action' => 'create', 'name' => 'email_template.create', 'display_name' => 'Create Email Templates'],
            ['module' => 'email_template', 'action' => 'update', 'name' => 'email_template.update', 'display_name' => 'Update Email Templates'],
            ['module' => 'email_template', 'action' => 'delete', 'name' => 'email_template.delete', 'display_name' => 'Delete Email Templates'],
            ['module' => 'activity_logs', 'action' => 'view', 'name' => 'activity_logs.view', 'display_name' => 'View Activity Logs'],
            ['module' => 'property', 'action' => 'view', 'name' => 'property.view', 'display_name' => 'View Properties'],
            ['module' => 'property', 'action' => 'create', 'name' => 'property.create', 'display_name' => 'Create Properties'],
            ['module' => 'property', 'action' => 'update', 'name' => 'property.update', 'display_name' => 'Update Properties'],
            ['module' => 'property', 'action' => 'delete', 'name' => 'property.delete', 'display_name' => 'Delete Properties'],
            ['module' => 'service', 'action' => 'view', 'name' => 'service.view', 'display_name' => 'View Services'],
            ['module' => 'service', 'action' => 'create', 'name' => 'service.create', 'display_name' => 'Create Services'],
            ['module' => 'service', 'action' => 'update', 'name' => 'service.update', 'display_name' => 'Update Services'],
            ['module' => 'service', 'action' => 'delete', 'name' => 'service.delete', 'display_name' => 'Delete Services'],
            ['module' => 'user', 'action' => 'view', 'name' => 'user.view', 'display_name' => 'View Users'],
            ['module' => 'user', 'action' => 'create', 'name' => 'user.create', 'display_name' => 'Create Users'],
            ['module' => 'user', 'action' => 'update', 'name' => 'user.update', 'display_name' => 'Update Users'],
            ['module' => 'user', 'action' => 'delete', 'name' => 'user.delete', 'display_name' => 'Delete Users'],
            ['module' => 'settings', 'action' => 'view', 'name' => 'settings.view', 'display_name' => 'View Settings'],
            ['module' => 'settings', 'action' => 'update', 'name' => 'settings.update', 'display_name' => 'Update Settings'],
            ['module' => 'permission', 'action' => 'view', 'name' => 'permission.view', 'display_name' => 'View Permissions'],
            ['module' => 'permission', 'action' => 'manage', 'name' => 'permission.manage', 'display_name' => 'Manage Permissions'],
        ];

        foreach ($definitions as $definition) {
            Permission::withTrashed()->updateOrCreate(
                ['name' => $definition['name']],
                [
                    'display_name' => $definition['display_name'],
                    'module' => $definition['module'],
                    'action' => $definition['action'],
                    'description' => null,
                    'guard_name' => 'api',
                    'is_system' => true,
                    'deleted_at' => null,
                ]
            );
        }

        $allPermissions = Permission::query()->pluck('id', 'name');

        $superAdminRole = Role::query()->firstOrCreate(
            ['name' => 'super_admin'],
            ['scope' => 'global', 'is_system' => true]
        );
        $this->syncRolePermissions($superAdminRole, $allPermissions->values()->all());

        $adminRole = Role::query()->firstOrCreate(
            ['name' => 'admin'],
            ['scope' => 'tenant', 'is_system' => true]
        );
        $this->syncRolePermissions($adminRole, $allPermissions->only([
            'dashboard.view',
            'property.view',
            'property.create',
            'property.update',
            'property.delete',
            'service.view',
            'service.create',
            'service.update',
            'service.delete',
            'user.view',
            'user.create',
            'user.update',
            'settings.view',
            'settings.update',
            'activity_logs.view',
            'order.view',
            'order.create',
            'order.cancel',
            'plan_request.view',
            'plan_request.create',
            'addon.view',
            'addon.create',
            'addon.update',
            'addon.delete',
            'referral.view',
            'billing.view',
            'billing.manage',
            'subscription.view',
            'dynamic_id.view',
            'dynamic_id.manage',
            'coupon.view',
            'activity_logs.view',
        ])->values()->all());

        $viewerRole = Role::query()->firstOrCreate(
            ['name' => 'viewer'],
            ['scope' => 'tenant', 'is_system' => true]
        );
        $this->syncRolePermissions($viewerRole, $allPermissions->only([
            'dashboard.view',
            'property.view',
            'user.view',
        ])->values()->all());
    }

    private function syncRolePermissions(Role $role, array $permissionIds): void
    {
        DB::table('role_permissions')->where('role_id', $role->id)->delete();

        foreach ($permissionIds as $permissionId) {
            DB::table('role_permissions')->insert([
                'id' => (string) Str::uuid(),
                'role_id' => $role->id,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
