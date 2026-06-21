<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['module' => 'service', 'action' => 'view', 'name' => 'service.view', 'display_name' => 'View Services'],
            ['module' => 'service', 'action' => 'create', 'name' => 'service.create', 'display_name' => 'Create Services'],
            ['module' => 'service', 'action' => 'update', 'name' => 'service.update', 'display_name' => 'Update Services'],
            ['module' => 'service', 'action' => 'delete', 'name' => 'service.delete', 'display_name' => 'Delete Services'],
        ];

        foreach ($permissions as $permission) {
            $existingId = DB::table('permissions')->where('name', $permission['name'])->value('id');

            if (!$existingId) {
                $existingId = (string) Str::uuid();

                DB::table('permissions')->insert([
                    'id' => $existingId,
                    'name' => $permission['name'],
                    'display_name' => $permission['display_name'],
                    'module' => $permission['module'],
                    'action' => $permission['action'],
                    'description' => null,
                    'guard_name' => 'api',
                    'is_system' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach (['super_admin', 'admin'] as $roleName) {
                $roleId = DB::table('roles')->where('name', $roleName)->value('id');

                if (!$roleId) {
                    continue;
                }

                $exists = DB::table('role_permissions')
                    ->where('role_id', $roleId)
                    ->where('permission_id', $existingId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('role_permissions')->insert([
                    'id' => (string) Str::uuid(),
                    'role_id' => $roleId,
                    'permission_id' => $existingId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionNames = ['service.view', 'service.create', 'service.update', 'service.delete'];

        $permissionIds = DB::table('permissions')
            ->whereIn('name', $permissionNames)
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }
    }
};
