<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'super_admin', 'scope' => 'global'],
            ['name' => 'admin', 'scope' => 'tenant'],
            ['name' => 'admin_staff', 'scope' => 'tenant'],
            ['name' => 'seeker', 'scope' => 'tenant'],
        ];

        foreach ($roles as $role) {
            $model = Role::withTrashed()->updateOrCreate(
                ['name' => $role['name']],
                [
                    'scope' => $role['scope'],
                    'is_system' => true,
                ]
            );

            if (method_exists($model, 'trashed') && $model->trashed()) {
                $model->restore();
            }
        }
    }
}
