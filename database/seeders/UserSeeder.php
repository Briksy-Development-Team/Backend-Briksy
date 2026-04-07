<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = Organization::all();

        foreach ($organizations as $org) {
            $this->upsertUser(
                $org->id,
                $org->slug . '@brisky.example',
                $org->name . ' Admin',
                $org->name . ' Admin'
            );

            $this->upsertUser(
                $org->id,
                'team+' . $org->slug . '@brisky.example',
                $org->name . ' Team',
                $org->name . ' Team Member'
            );
        }
    }

    private function upsertUser(string $organizationId, string $email, string $name, string $displayName): void
    {
        User::withTrashed()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'display_name' => $displayName,
                'password_hash' => Hash::make('password'),
                'organization_id' => $organizationId,
                'email_verified_at' => now(),
                'mobile_number' => null,
                'mobile_verified_at' => null,
                'id_verified' => true,
                'deleted_at' => null,
            ]
        );
    }
}
