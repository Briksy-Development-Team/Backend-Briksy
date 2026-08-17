<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Role;
use App\Models\SeekerProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SeekerSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'seeker'],
            ['scope' => 'global', 'is_system' => true]
        );

        foreach ($this->globalSeekers() as $data) {
            $this->upsertSeeker($role, $data);
        }

        Organization::query()
            ->orderBy('name')
            ->get()
            ->values()
            ->each(function (Organization $organization, int $index) use ($role): void {
                foreach ($this->organizationSeekers($organization, $index) as $data) {
                    $data['organization_id'] = $organization->id;
                    $this->upsertSeeker($role, $data);
                }
            });
    }

    private function upsertSeeker(Role $role, array $data): User
    {
        $user = User::withTrashed()->updateOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'],
                'display_name' => $data['display_name'] ?? $data['name'],
                'password_hash' => 'Qwerty@123',
                'organization_id' => $data['organization_id'] ?? null,
                'email_verified_at' => now(),
                'mobile_number' => $data['mobile_number'] ?? null,
                'mobile_verified_at' => $data['mobile_verified_at'] ?? null,
                'id_verified' => $data['id_verified'] ?? true,
                'deleted_at' => null,
            ]
        );

        if (method_exists($user, 'trashed') && $user->trashed()) {
            $user->restore();
            $user->refresh();
        }

        $this->attachRole($user, $role, $data['organization_id'] ?? null);

        if (isset($data['postcode'])) {
            SeekerProfile::withTrashed()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'current_postcode' => $data['postcode'],
                    'preferred_budget_min' => $data['preferred_budget_min'] ?? null,
                    'preferred_budget_max' => $data['preferred_budget_max'] ?? null,
                    'deleted_at' => null,
                ]
            );
        }

        return $user;
    }

    private function attachRole(User $user, Role $role, ?string $organizationId): void
    {
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

    private function globalSeekers(): array
    {
        return [
            [
                'name' => 'Olivia Parker',
                'display_name' => 'Olivia Parker',
                'email' => 'olivia.parker@example.com',
                'mobile_number' => '+61 400 111 201',
                'postcode' => '2000',
                'preferred_budget_min' => 550000,
                'preferred_budget_max' => 820000,
            ],
            [
                'name' => 'Ethan Brooks',
                'display_name' => 'Ethan Brooks',
                'email' => 'ethan.brooks@example.com',
                'mobile_number' => '+61 400 111 202',
                'postcode' => '3000',
                'preferred_budget_min' => 420000,
                'preferred_budget_max' => 700000,
            ],
        ];
    }

    private function organizationSeekers(Organization $organization, int $organizationIndex): array
    {
        $slug = $organization->slug;

        return [
            [
                'name' => ucfirst(str_replace('-', ' ', $slug)) . ' Client One',
                'display_name' => ucfirst(str_replace('-', ' ', $slug)) . ' Client One',
                'email' => 'seeker1+' . $slug . '@example.com',
                'mobile_number' => $this->mobileNumberFor($organizationIndex, 1),
                'postcode' => (string) $this->postcodeFromSlug($slug, 2000),
                'preferred_budget_min' => $this->budgetFromSlug($slug, 350000),
                'preferred_budget_max' => $this->budgetFromSlug($slug, 700000),
                'organization_id' => $organization->id,
            ],
            [
                'name' => ucfirst(str_replace('-', ' ', $slug)) . ' Client Two',
                'display_name' => ucfirst(str_replace('-', ' ', $slug)) . ' Client Two',
                'email' => 'seeker2+' . $slug . '@example.com',
                'mobile_number' => $this->mobileNumberFor($organizationIndex, 2),
                'postcode' => (string) $this->postcodeFromSlug($slug, 2500),
                'preferred_budget_min' => $this->budgetFromSlug($slug, 450000),
                'preferred_budget_max' => $this->budgetFromSlug($slug, 900000),
                'organization_id' => $organization->id,
            ],
        ];
    }

    private function mobileNumberFor(int $organizationIndex, int $clientIndex): string
    {
        $middle = str_pad((string) (200 + ($organizationIndex * 2) + $clientIndex), 3, '0', STR_PAD_LEFT);
        $tail = str_pad((string) (10 + $organizationIndex + $clientIndex), 2, '0', STR_PAD_LEFT);

        return "+61 400 {$middle} {$tail}";
    }

    private function postcodeFromSlug(string $slug, int $offset): int
    {
        return $offset + (crc32($slug) % 1000);
    }

    private function budgetFromSlug(string $slug, int $offset): int
    {
        return $offset + (crc32($slug) % 250000);
    }
}
