<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $definitions = [
            ['module' => 'project', 'action' => 'view', 'name' => 'project.view', 'display_name' => 'View Projects'],
            ['module' => 'project', 'action' => 'create', 'name' => 'project.create', 'display_name' => 'Create Projects'],
            ['module' => 'project', 'action' => 'update', 'name' => 'project.update', 'display_name' => 'Update Projects'],
            ['module' => 'project', 'action' => 'delete', 'name' => 'project.delete', 'display_name' => 'Delete Projects'],
        ];

        foreach ($definitions as $definition) {
            Permission::withTrashed()->updateOrCreate(
                ['name' => $definition['name']],
                [
                    'display_name' => $definition['display_name'],
                    'module' => $definition['module'],
                    'action' => $definition['action'],
                    'guard_name' => 'api',
                    'is_system' => true,
                    'deleted_at' => null,
                ]
            );
        }

        $permissionIds = Permission::query()
            ->whereIn('name', [
                'project.view',
                'project.create',
                'project.update',
                'project.delete',
                'property.create',
                'property.update',
                'property.delete',
            ])
            ->pluck('id')
            ->all();

        Role::query()
            ->whereIn('name', ['admin', 'admin_staff'])
            ->get()
            ->each(function (Role $role) use ($permissionIds): void {
                foreach ($permissionIds as $permissionId) {
                    DB::table('role_permissions')->updateOrInsert(
                        [
                            'role_id' => $role->id,
                            'permission_id' => $permissionId,
                        ],
                        [
                            'id' => (string) str()->uuid(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            });
    }

    public function down(): void
    {
        $permissionIds = Permission::query()
            ->whereIn('name', [
                'project.view',
                'project.create',
                'project.update',
                'project.delete',
            ])
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
            Permission::query()->whereIn('id', $permissionIds)->delete();
        }
    }
};
