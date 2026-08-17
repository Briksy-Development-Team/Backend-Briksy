<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::withTrashed()->updateOrCreate(
            ['email' => 'superadmin@brisky.example'],
            [
                'name' => 'Briksy Super Admin',
                'display_name' => 'Super Admin',
                'password_hash' => 'Qwerty@123',
                'organization_id' => null,
                'email_verified_at' => now(),
                'mobile_number' => null,
                'mobile_verified_at' => null,
                'id_verified' => true,
                'deleted_at' => null,
            ]
        );

        if (method_exists($superAdmin, 'trashed') && $superAdmin->trashed()) {
            $superAdmin->restore();
            $superAdmin->refresh();
        }

        $role = Role::query()->firstOrCreate(
            ['name' => 'super_admin'],
            ['scope' => 'global', 'is_system' => true]
        );

        if (!$superAdmin->roles()->where('roles.id', $role->id)->exists()) {
            $superAdmin->roles()->attach($role->id, [
                'id' => (string) Str::uuid(),
                'organization_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $superAdmin->roles()->updateExistingPivot($role->id, [
                'organization_id' => null,
                'updated_at' => now(),
            ]);
        }
    }
}
