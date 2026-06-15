<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Role;
use App\Models\SeekerProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminPortalSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = Organization::query()->orderBy('name')->get();

        foreach ($organizations as $organization) {
            $admin = User::query()
                ->where('organization_id', $organization->id)
                ->whereHas('roles', fn ($query) => $query->where('name', 'admin'))
                ->first();

            if (!$admin) {
                continue;
            }

            $this->seedCompanySettings($organization);
            $this->seedStaff($organization, $admin);
            $this->seedSeekers($organization);
            $this->seedProperties($organization, $admin);
        }
    }

    private function seedCompanySettings(Organization $organization): void
    {
        $settings = [
            ['key' => 'company_name', 'value' => $organization->name, 'type' => 'string', 'group' => 'company', 'label' => 'Company Name'],
            ['key' => 'support_email', 'value' => $organization->contact_email, 'type' => 'string', 'group' => 'company', 'label' => 'Support Email'],
            ['key' => 'contact_phone', 'value' => $organization->contact_phone, 'type' => 'string', 'group' => 'company', 'label' => 'Contact Phone'],
            ['key' => 'property_refresh_days', 'value' => '30', 'type' => 'number', 'group' => 'property', 'label' => 'Property Refresh Days'],
        ];

        foreach ($settings as $setting) {
            DB::table('company_settings')->updateOrInsert(
                ['organization_id' => $organization->id, 'key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'type' => $setting['type'],
                    'group' => $setting['group'],
                    'label' => $setting['label'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    private function seedStaff(Organization $organization, User $admin): void
    {
        $staffMembers = [
            [
                'name' => $organization->name . ' Operations',
                'email' => 'ops+' . $organization->slug . '@brisky.example',
                'display_name' => 'Operations Team',
            ],
            [
                'name' => $organization->name . ' Support',
                'email' => 'support+' . $organization->slug . '@brisky.example',
                'display_name' => 'Support Team',
            ],
        ];

        $role = Role::query()->firstOrCreate(
            ['name' => 'admin_staff'],
            ['scope' => 'tenant', 'is_system' => true]
        );

        foreach ($staffMembers as $member) {
            $user = User::withTrashed()->updateOrCreate(
                ['email' => $member['email']],
                [
                    'name' => $member['name'],
                    'display_name' => $member['display_name'],
                    'password_hash' => 'password',
                    'organization_id' => $organization->id,
                    'email_verified_at' => now(),
                    'mobile_number' => null,
                    'mobile_verified_at' => null,
                    'id_verified' => false,
                    'deleted_at' => null,
                ]
            );

            if ($user->trashed()) {
                $user->restore();
                $user->refresh();
            }

            if (!$user->roles()->where('roles.id', $role->id)->exists()) {
                $user->roles()->attach($role->id, [
                    'id' => (string) Str::uuid(),
                    'organization_id' => $organization->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function seedSeekers(Organization $organization): void
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'seeker'],
            ['scope' => 'global', 'is_system' => true]
        );

        $seekers = [
            [
                'name' => $organization->name . ' Customer One',
                'email' => 'customer1+' . $organization->slug . '@example.com',
                'postcode' => '20' . substr((string) crc32($organization->slug), 0, 2),
            ],
            [
                'name' => $organization->name . ' Customer Two',
                'email' => 'customer2+' . $organization->slug . '@example.com',
                'postcode' => '30' . substr((string) crc32($organization->slug), 0, 2),
            ],
        ];

        foreach ($seekers as $data) {
            $user = User::withTrashed()->updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'display_name' => $data['name'],
                    'password_hash' => 'password',
                    'organization_id' => $organization->id,
                    'email_verified_at' => now(),
                    'mobile_number' => null,
                    'mobile_verified_at' => null,
                    'id_verified' => true,
                    'deleted_at' => null,
                ]
            );

            if ($user->trashed()) {
                $user->restore();
                $user->refresh();
            }

            if (!$user->roles()->where('roles.id', $role->id)->exists()) {
                $user->roles()->attach($role->id, [
                    'id' => (string) Str::uuid(),
                    'organization_id' => $organization->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            SeekerProfile::withTrashed()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'current_postcode' => $data['postcode'],
                    'preferred_budget_min' => 450000,
                    'preferred_budget_max' => 900000,
                    'deleted_at' => null,
                ]
            );
        }
    }

    private function seedProperties(Organization $organization, User $admin): void
    {
        $templates = [
            [
                'title' => $organization->name . ' Family Home',
                'description' => 'Demo property for admin property management.',
                'status' => 'Published',
                'suburb' => 'Central',
                'postcode' => '2000',
                'latitude' => -33.865143,
                'longitude' => 151.209900,
            ],
            [
                'title' => $organization->name . ' Investment Opportunity',
                'description' => 'Second demo property for management and filtering.',
                'status' => 'Draft',
                'suburb' => 'Harbour',
                'postcode' => '2001',
                'latitude' => -33.870000,
                'longitude' => 151.220000,
            ],
        ];

        foreach ($templates as $template) {
            $existing = DB::table('property_listings')
                ->where('org_id', $organization->id)
                ->where('title', $template['title'])
                ->first();

            $payload = [
                'org_id' => $organization->id,
                'creator_id' => $admin->id,
                'avg_prop_rating' => 4.1,
                'latitude' => $template['latitude'],
                'longitude' => $template['longitude'],
                'title' => $template['title'],
                'description' => $template['description'],
                'status' => $template['status'],
                'suburb' => $template['suburb'],
                'postcode' => $template['postcode'],
                'embedding' => null,
                'property_type_id' => DB::table('property_types')->value('id'),
                'property_condition' => 'established',
                'land_area_sqm' => 380.00,
                'floor_area_sqm' => 160.00,
                'frontage_width_m' => 12.00,
                'bedroom_option' => '3',
                'bathroom_option' => '2',
                'car_space_option' => '1',
                'deleted_at' => null,
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('property_listings')->where('id', $existing->id)->update($payload);
                continue;
            }

            DB::table('property_listings')->insert(array_merge($payload, [
                'id' => (string) Str::uuid(),
                'created_at' => now(),
            ]));
        }
    }
}
