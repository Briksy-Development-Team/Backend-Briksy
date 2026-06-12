<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('briksy:sync-admin-role {email=admin@example.com} {--strip-super-admin : Remove the super_admin role if present}', function () {
    $email = (string) $this->argument('email');
    $stripSuperAdmin = (bool) $this->option('strip-super-admin');

    $user = User::query()->where('email', $email)->first();

    if (!$user) {
        $this->error("User not found: {$email}");

        return self::FAILURE;
    }

    $adminRole = Role::query()->firstOrCreate(
        ['name' => 'admin'],
        ['scope' => 'global', 'is_system' => true]
    );

    $superAdminRole = Role::query()->firstOrCreate(
        ['name' => 'super_admin'],
        ['scope' => 'global', 'is_system' => true]
    );

    DB::transaction(function () use ($user, $adminRole, $superAdminRole, $stripSuperAdmin): void {
        $user->roles()->syncWithoutDetaching([
            $adminRole->id => [
                'id' => (string) Str::uuid(),
                'organization_id' => $user->organization_id,
            ],
        ]);

        if ($stripSuperAdmin) {
            $user->roles()->detach($superAdminRole->id);
        }
    });

    $user->load('roles');

    $this->info(sprintf(
        'Normalized %s. Roles now: %s',
        $email,
        $user->roles->pluck('name')->join(', ')
    ));

    return self::SUCCESS;
})->purpose('Normalize a user to the admin role and optionally remove super_admin.');
