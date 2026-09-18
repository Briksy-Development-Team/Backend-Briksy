<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', [
                'property.approve',
                'property.reject',
                'property.publish',
                'property.archive',
                'property.verify_location',
                'property.unverify_location',
            ])
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        $adminStaffRoleIds = DB::table('roles')
            ->where('name', 'admin_staff')
            ->pluck('id');

        if ($adminStaffRoleIds->isNotEmpty()) {
            DB::table('role_permissions')
                ->whereIn('role_id', $adminStaffRoleIds)
                ->whereIn('permission_id', $permissionIds)
                ->delete();
        }

        DB::table('user_permissions')
            ->whereIn('permission_id', $permissionIds)
            ->whereIn('user_id', function ($query) use ($adminStaffRoleIds): void {
                $query->select('user_id')
                    ->from('user_roles')
                    ->whereIn('role_id', $adminStaffRoleIds)
                    ->whereNull('deleted_at');
            })
            ->delete();
    }

    public function down(): void
    {
        // Existing grants are intentionally not restored. These permissions
        // remain available to the platform catalogue and Super Admin roles.
    }
};
