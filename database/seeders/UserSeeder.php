<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = Organization::all();

        foreach ($organizations as $org) {
            $admin = $this->upsertUser(
                $org->id,
                $org->slug . '@brisky.example',
                $org->name . ' Admin',
                $org->name . ' Admin'
            );

            $staff = $this->upsertUser(
                $org->id,
                'team+' . $org->slug . '@brisky.example',
                $org->name . ' Team',
                $org->name . ' Team Member'
            );

            $this->attachRole($admin, 'admin', $org->id);
            $this->attachRole($staff, 'admin_staff', $org->id);
        }
    }

    private function upsertUser(string $organizationId, string $email, string $name, string $displayName): User
    {
        $user = User::withTrashed()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'display_name' => $displayName,
                'password_hash' => 'password',
                'organization_id' => $organizationId,
                'email_verified_at' => now(),
                'mobile_number' => null,
                'mobile_verified_at' => null,
                'id_verified' => true,
                'deleted_at' => null,
            ]
        );

        if (method_exists($user, 'trashed') && $user->trashed()) {
            $user->restore();
            $user->refresh();
        }

        return $user;
    }

    private function attachRole(User $user, string $roleName, string $organizationId): void
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName],
            ['scope' => 'tenant', 'is_system' => true]
        );

        if ($user->roles()->where('roles.id', $role->id)->exists()) {
            $user->roles()->updateExistingPivot($role->id, [
                'organization_id' => $organizationId,
                'updated_at' => now(),
            ]);

            return;
        }

        $user->roles()->attach($role->id, [
            'id' => (string) Str::uuid(),
            'organization_id' => $organizationId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
