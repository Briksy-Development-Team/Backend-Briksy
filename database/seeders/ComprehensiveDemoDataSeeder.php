<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ComprehensiveDemoDataSeeder extends Seeder
{
    private const MIN_COUNT = 20;

    public function run(): void
    {
        $this->seedOrganizationTypes();
        $this->seedSubscriptionPlans();
        $this->seedRoles();
        $this->seedPermissions();
        $this->seedRolePermissions();

        $this->seedOrganizations();
        $this->seedUsers();
        $this->seedUserRoles();
        $this->seedSubscriptions();

        $this->seedServiceGroups();
        $this->seedServices();
        $this->seedServiceGroupServices();
        $this->seedOrganizationServices();
        $this->seedOrganizationServiceGroups();

        $this->seedPropertyTypes();
        $this->seedPropertyFeatureGroups();
        $this->seedPropertyFeatures();
        $this->seedPropertyListings();
        $this->seedPropertyListingFeatures();

        $this->seedSeekerProfiles();
        $this->seedSeekerSavedSearches();
        $this->seedUserCommunicationPreferences();
        $this->seedSoleTraderProfiles();
        $this->seedSocialAccounts();

        $this->seedServiceAttributeDefinitions();
        $this->seedServiceComplianceRequirements();
        $this->seedOrganizationServiceCompliances();

        $this->seedInquiries();
        $this->seedReviews();
        $this->seedFavorites();
        $this->seedVerificationDocuments();
        $this->seedVisitorLogs();
        $this->seedSystemIssues();
        $this->seedActivityLogs();
        $this->seedApiTokens();
        $this->seedPersonalAccessTokens();
        $this->seedMedia();
    }

    private function seedOrganizationTypes(): void
    {
        $existing = DB::table('organization_types')->count();

        for ($i = $existing + 1; $i <= self::MIN_COUNT; $i++) {
            DB::table('organization_types')->insert([
                'id' => (string) Str::uuid(),
                'name' => "Organization Type {$i}",
                'slug' => "organization-type-{$i}",
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    private function seedSubscriptionPlans(): void
    {
        $existing = DB::table('subscription_plans')->count();

        for ($i = $existing + 1; $i <= self::MIN_COUNT; $i++) {
            DB::table('subscription_plans')->insert([
                'id' => (string) Str::uuid(),
                'name' => "Plan {$i}",
                'stripe_price_id' => "price_demo_{$i}",
                'staff_seat_limit' => 3 + $i,
                'has_visitor_analytics' => $i % 2 === 0,
                'ranking_priority' => max(1, 21 - $i),
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    private function seedRoles(): void
    {
        $systemRoles = [
            ['name' => 'super_admin', 'scope' => 'global', 'is_system' => true],
            ['name' => 'admin', 'scope' => 'global', 'is_system' => true],
            ['name' => 'admin_staff', 'scope' => 'tenant', 'is_system' => true],
            ['name' => 'seeker', 'scope' => 'global', 'is_system' => true],
            ['name' => 'provider', 'scope' => 'tenant', 'is_system' => true],
        ];

        foreach ($systemRoles as $role) {
            $existingRole = DB::table('roles')->where('name', $role['name'])->first();

            if ($existingRole) {
                DB::table('roles')->where('id', $existingRole->id)->update([
                    'scope' => $role['scope'],
                    'is_system' => $role['is_system'],
                    'updated_at' => now(),
                    'deleted_at' => null,
                ]);
                continue;
            }

            DB::table('roles')->insert([
                'id' => (string) Str::uuid(),
                'name' => $role['name'],
                'scope' => $role['scope'],
                'is_system' => $role['is_system'],
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }

        $existing = DB::table('roles')->count();

        for ($i = $existing + 1; $i <= self::MIN_COUNT; $i++) {
            DB::table('roles')->insert([
                'id' => (string) Str::uuid(),
                'name' => "custom_role_{$i}",
                'scope' => $i % 2 === 0 ? 'global' : 'tenant',
                'is_system' => false,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    private function seedPermissions(): void
    {
        $modules = ['users', 'organizations', 'properties', 'services', 'analytics'];
        $existing = DB::table('permissions')->count();

        for ($i = $existing + 1; $i <= self::MIN_COUNT; $i++) {
            DB::table('permissions')->insert([
                'id' => (string) Str::uuid(),
                'name' => "permission_{$i}",
                'module' => $modules[$i % count($modules)],
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    private function seedRolePermissions(): void
    {
        $roles = DB::table('roles')->pluck('id');
        $permissions = DB::table('permissions')->pluck('id');

        $this->fillByPairs(
            'role_permissions',
            self::MIN_COUNT,
            fn (): array => ['role_id' => $roles->random(), 'permission_id' => $permissions->random()],
            fn (array $pair): bool => DB::table('role_permissions')
                ->where('role_id', $pair['role_id'])
                ->where('permission_id', $pair['permission_id'])
                ->exists(),
            fn (array $pair): array => array_merge($pair, [
                'id' => (string) Str::uuid(),
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ])
        );
    }

    private function seedOrganizations(): void
    {
        $types = DB::table('organization_types')->pluck('id');
        $plans = DB::table('subscription_plans')->pluck('id');
        $existing = DB::table('organizations')->count();

        for ($i = $existing + 1; $i <= self::MIN_COUNT; $i++) {
            DB::table('organizations')->insert([
                'id' => (string) Str::uuid(),
                'plan_id' => $plans->random(),
                'type_id' => $types->random(),
                'ranking_priority' => rand(1, 20),
                'avg_org_rating' => number_format(mt_rand(30, 50) / 10, 2, '.', ''),
                'name' => "Demo Organization {$i}",
                'slug' => "demo-organization-{$i}",
                'abn' => str_pad((string) (50000000000 + $i), 11, '0', STR_PAD_LEFT),
                'acn' => str_pad((string) (700000000 + $i), 9, '0', STR_PAD_LEFT),
                'contact_email' => "org{$i}@example.com",
                'contact_phone' => '+61 400 ' . str_pad((string) (100000 + $i), 6, '0', STR_PAD_LEFT),
                'stripe_customer_id' => "cus_demo_{$i}",
                'is_verified' => $i % 2 === 0,
                'logo_url' => "https://example.com/logo/org-{$i}.png",
                'brand_primary_color' => sprintf('#%06X', rand(0, 0xFFFFFF)),
                'brand_secondary_color' => sprintf('#%06X', rand(0, 0xFFFFFF)),
                'licensed_staff_seats' => rand(2, 50),
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    private function seedUsers(): void
    {
        $organizations = DB::table('organizations')->pluck('id');
        $existing = DB::table('users')->count();

        for ($i = $existing + 1; $i <= (self::MIN_COUNT * 2); $i++) {
            DB::table('users')->insert([
                'id' => (string) Str::uuid(),
                'organization_id' => $i % 3 === 0 ? null : $organizations->random(),
                'name' => "Demo User {$i}",
                'display_name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'mobile_number' => '+614' . str_pad((string) (10000000 + $i), 8, '0', STR_PAD_LEFT),
                'email_verified_at' => now(),
                'mobile_verified_at' => now(),
                'password_hash' => Hash::make('password'),
                'id_verified' => $i % 2 === 0,
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    private function seedUserRoles(): void
    {
        $users = DB::table('users')->select('id', 'organization_id')->get();
        $roles = DB::table('roles')->pluck('id', 'name');

        $priorityRoles = ['super_admin', 'admin', 'admin_staff', 'seeker'];
        foreach ($priorityRoles as $index => $roleName) {
            $roleId = $roles->get($roleName);
            $user = $users[$index] ?? null;

            if (!$roleId || !$user) {
                continue;
            }

            $orgId = $roleName === 'admin_staff' ? $user->organization_id : null;
            $exists = DB::table('user_roles')
                ->where('user_id', $user->id)
                ->where('role_id', $roleId)
                ->where(function ($q) use ($orgId): void {
                    if ($orgId === null) {
                        $q->whereNull('organization_id');
                    } else {
                        $q->where('organization_id', $orgId);
                    }
                })
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('user_roles')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'role_id' => $roleId,
                'organization_id' => $orgId,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }

        $roleIds = DB::table('roles')->pluck('id');
        $this->fillByPairs(
            'user_roles',
            self::MIN_COUNT * 2,
            function () use ($users, $roleIds): array {
                $user = $users->random();
                return [
                    'user_id' => $user->id,
                    'role_id' => $roleIds->random(),
                    'organization_id' => $user->organization_id,
                ];
            },
            fn (array $pair): bool => DB::table('user_roles')
                ->where('user_id', $pair['user_id'])
                ->where('role_id', $pair['role_id'])
                ->where(function ($q) use ($pair): void {
                    if ($pair['organization_id'] === null) {
                        $q->whereNull('organization_id');
                    } else {
                        $q->where('organization_id', $pair['organization_id']);
                    }
                })
                ->exists(),
            fn (array $pair): array => array_merge($pair, [
                'id' => (string) Str::uuid(),
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ])
        );
    }

    private function seedSubscriptions(): void
    {
        $organizations = DB::table('organizations')->pluck('id');
        $plans = DB::table('subscription_plans')->pluck('id');
        $existing = DB::table('subscriptions')->count();
        $statuses = ['active', 'canceled', 'past_due'];

        for ($i = $existing + 1; $i <= self::MIN_COUNT; $i++) {
            $start = Carbon::now()->subDays(rand(1, 180));
            $end = (clone $start)->addMonth();

            DB::table('subscriptions')->insert([
                'id' => (string) Str::uuid(),
                'organization_id' => $organizations->random(),
                'subscription_plan_id' => $plans->random(),
                'stripe_subscription_id' => "sub_demo_{$i}",
                'status' => $statuses[$i % count($statuses)],
                'current_period_start' => $start,
                'current_period_end' => $end,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    private function seedServiceGroups(): void
    {
        $types = DB::table('organization_types')->pluck('id');
        $existing = DB::table('service_groups')->count();

        for ($i = $existing + 1; $i <= self::MIN_COUNT; $i++) {
            DB::table('service_groups')->insert([
                'id' => (string) Str::uuid(),
                'type_id' => $types->random(),
                'name' => "Service Group {$i}",
                'slug' => "service-group-{$i}",
                'description' => "Demo service group {$i}",
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    private function seedServices(): void
    {
        $types = DB::table('organization_types')->pluck('id');
        $groups = DB::table('service_groups')->pluck('id');
        $existing = DB::table('services')->count();

        for ($i = $existing + 1; $i <= self::MIN_COUNT; $i++) {
            DB::table('services')->insert([
                'id' => (string) Str::uuid(),
                'type_id' => $types->random(),
                'service_group_id' => $groups->random(),
                'name' => "Service {$i}",
                'slug' => "service-{$i}",
                'description' => "Demo service {$i}",
                'is_active' => $i % 4 !== 0,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    private function seedServiceGroupServices(): void
    {
        $groupIds = DB::table('service_groups')->pluck('id');
        $serviceIds = DB::table('services')->pluck('id');

        $this->fillByPairs(
            'service_group_services',
            self::MIN_COUNT,
            fn (): array => ['service_group_id' => $groupIds->random(), 'service_id' => $serviceIds->random()],
            fn (array $pair): bool => DB::table('service_group_services')
                ->where('service_group_id', $pair['service_group_id'])
                ->where('service_id', $pair['service_id'])
                ->exists(),
            fn (array $pair): array => array_merge($pair, [
                'id' => (string) Str::uuid(),
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ])
        );
    }

    private function seedOrganizationServices(): void
    {
        $organizationIds = DB::table('organizations')->pluck('id');
        $serviceIds = DB::table('services')->pluck('id');

        $this->fillByPairs(
            'organization_services',
            self::MIN_COUNT,
            fn (): array => ['organization_id' => $organizationIds->random(), 'service_id' => $serviceIds->random()],
            fn (array $pair): bool => DB::table('organization_services')
                ->where('organization_id', $pair['organization_id'])
                ->where('service_id', $pair['service_id'])
                ->exists(),
            fn (array $pair): array => array_merge($pair, [
                'id' => (string) Str::uuid(),
                'description' => 'Demo service offering',
                'starting_price' => rand(80, 1500),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ])
        );
    }

    private function seedOrganizationServiceGroups(): void
    {
        $organizationIds = DB::table('organizations')->pluck('id');
        $groupIds = DB::table('service_groups')->pluck('id');

        $this->fillByPairs(
            'organization_service_groups',
            self::MIN_COUNT,
            fn (): array => ['organization_id' => $organizationIds->random(), 'service_group_id' => $groupIds->random()],
            fn (array $pair): bool => DB::table('organization_service_groups')
                ->where('organization_id', $pair['organization_id'])
                ->where('service_group_id', $pair['service_group_id'])
                ->exists(),
            fn (array $pair): array => array_merge($pair, [
                'id' => (string) Str::uuid(),
                'description' => 'Demo group package',
                'package_price' => rand(200, 4000),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ])
        );
    }

    private function seedPropertyTypes(): void
    {
        $existing = DB::table('property_types')->count();

        for ($i = $existing + 1; $i <= self::MIN_COUNT; $i++) {
            DB::table('property_types')->insert([
                'id' => (string) Str::uuid(),
                'name' => "Property Type {$i}",
                'slug' => "property-type-{$i}",
                'sort_order' => $i,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    private function seedPropertyFeatureGroups(): void
    {
        $existing = DB::table('property_feature_groups')->count();

        for ($i = $existing + 1; $i <= self::MIN_COUNT; $i++) {
            DB::table('property_feature_groups')->insert([
                'id' => (string) Str::uuid(),
                'name' => "Feature Group {$i}",
                'slug' => "feature-group-{$i}",
                'sort_order' => $i,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    private function seedPropertyFeatures(): void
    {
        $groupIds = DB::table('property_feature_groups')->pluck('id');
        $existing = DB::table('property_features')->count();

        for ($i = $existing + 1; $i <= self::MIN_COUNT; $i++) {
            DB::table('property_features')->insert([
                'id' => (string) Str::uuid(),
                'group_id' => $groupIds->random(),
                'name' => "Feature {$i}",
                'slug' => "feature-{$i}",
                'sort_order' => $i,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    private function seedPropertyListings(): void
    {
        $organizations = DB::table('organizations')->pluck('id');
        $users = DB::table('users')->pluck('id');
        $propertyTypes = DB::table('property_types')->pluck('id');
        $statuses = ['Draft', 'Published', 'Archived'];
        $conditions = ['new', 'established'];
        $bedrooms = ['studio', '1', '2', '3', '4', '5_plus'];
        $bathrooms = ['1', '2', '3_plus'];
        $carSpaces = ['1', '2', '3_plus'];
        $existing = DB::table('property_listings')->count();

        for ($i = $existing + 1; $i <= self::MIN_COUNT; $i++) {
            DB::table('property_listings')->insert([
                'id' => (string) Str::uuid(),
                'org_id' => $organizations->random(),
                'creator_id' => $users->random(),
                'avg_prop_rating' => number_format(mt_rand(30, 50) / 10, 2, '.', ''),
                'latitude' => number_format(-38 + lcg_value() * 12, 7, '.', ''),
                'longitude' => number_format(113 + lcg_value() * 40, 7, '.', ''),
                'title' => "Demo Property {$i}",
                'description' => "Demo property listing description {$i}",
                'status' => $statuses[$i % count($statuses)],
                'property_type_id' => $propertyTypes->random(),
                'property_condition' => $conditions[$i % count($conditions)],
                'suburb' => "Suburb {$i}",
                'postcode' => (string) rand(2000, 7999),
                'land_area_sqm' => number_format(rand(100, 2000) + (rand(0, 99) / 100), 2, '.', ''),
                'floor_area_sqm' => number_format(rand(50, 700) + (rand(0, 99) / 100), 2, '.', ''),
                'frontage_width_m' => number_format(rand(5, 40) + (rand(0, 99) / 100), 2, '.', ''),
                'bedroom_option' => $bedrooms[$i % count($bedrooms)],
                'bathroom_option' => $bathrooms[$i % count($bathrooms)],
                'car_space_option' => $carSpaces[$i % count($carSpaces)],
                'embedding' => null,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    private function seedPropertyListingFeatures(): void
    {
        $listingIds = DB::table('property_listings')->pluck('id');
        $featureIds = DB::table('property_features')->pluck('id');

        $this->fillByPairs(
            'property_listing_features',
            self::MIN_COUNT,
            fn (): array => ['property_listing_id' => $listingIds->random(), 'feature_id' => $featureIds->random()],
            fn (array $pair): bool => DB::table('property_listing_features')
                ->where('property_listing_id', $pair['property_listing_id'])
                ->where('feature_id', $pair['feature_id'])
                ->exists(),
            fn (array $pair): array => array_merge($pair, [
                'id' => (string) Str::uuid(),
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ])
        );
    }

    private function seedSeekerProfiles(): void
    {
        $seekerRoleId = DB::table('roles')->where('name', 'seeker')->value('id');
        if (!$seekerRoleId) {
            return;
        }

        $seekerUsers = DB::table('user_roles')->where('role_id', $seekerRoleId)->pluck('user_id');
        if ($seekerUsers->count() < self::MIN_COUNT) {
            $additional = DB::table('users')
                ->whereNotIn('id', $seekerUsers)
                ->limit(self::MIN_COUNT - $seekerUsers->count())
                ->pluck('id');

            foreach ($additional as $userId) {
                $exists = DB::table('user_roles')
                    ->where('user_id', $userId)
                    ->where('role_id', $seekerRoleId)
                    ->whereNull('organization_id')
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('user_roles')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $userId,
                    'role_id' => $seekerRoleId,
                    'organization_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                ]);
            }

            $seekerUsers = DB::table('user_roles')->where('role_id', $seekerRoleId)->pluck('user_id');
        }

        $existingUserIds = DB::table('seeker_profiles')->pluck('user_id');
        foreach ($seekerUsers->take(self::MIN_COUNT) as $userId) {
            if ($existingUserIds->contains($userId)) {
                continue;
            }

            DB::table('seeker_profiles')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'current_postcode' => (string) rand(2000, 7999),
                'preferred_budget_min' => rand(200000, 700000),
                'preferred_budget_max' => rand(700001, 2000000),
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    private function seedSeekerSavedSearches(): void
    {
        $userIds = DB::table('seeker_profiles')->pluck('user_id');
        $existing = DB::table('seeker_saved_searches')->count();

        for ($i = $existing + 1; $i <= self::MIN_COUNT; $i++) {
            DB::table('seeker_saved_searches')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $userIds->random(),
                'name' => "Saved Search {$i}",
                'budget_min' => rand(200000, 600000),
                'budget_max' => rand(650000, 2000000),
                'location_json' => json_encode(['postcode' => (string) rand(2000, 7999)]),
                'property_types_json' => json_encode(['house', 'townhouse']),
                'filters_json' => json_encode(['bedrooms' => ['2', '3']]),
                'is_default' => $i % 10 === 0,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    private function seedUserCommunicationPreferences(): void
    {
        $users = DB::table('users')->pluck('id');
        $existingUserIds = DB::table('user_communication_preferences')->pluck('user_id');

        foreach ($users->take(self::MIN_COUNT) as $index => $userId) {
            if ($existingUserIds->contains($userId)) {
                continue;
            }

            DB::table('user_communication_preferences')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'sms_enabled' => $index % 2 === 0,
                'email_enabled' => true,
                'push_enabled' => $index % 3 !== 0,
                'marketing_opt_in' => $index % 4 === 0,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    private function seedSoleTraderProfiles(): void
    {
        $organizations = DB::table('organizations')->pluck('id');
        $users = DB::table('users')
            ->whereNotIn('id', DB::table('sole_trader_profiles')->pluck('user_id'))
            ->limit(self::MIN_COUNT)
            ->pluck('id');

        foreach ($users as $i => $userId) {
            DB::table('sole_trader_profiles')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'organization_id' => $i % 2 === 0 ? $organizations->random() : null,
                'trading_name' => "Sole Trader {$i}",
                'abn' => str_pad((string) (81000000000 + $i), 11, '0', STR_PAD_LEFT),
                'trade_license_number' => "TLN-{$i}",
                'primary_service_postcode' => (string) rand(2000, 7999),
                'service_radius_km' => rand(5, 100),
                'profile_image_url' => "https://example.com/sole-trader/{$i}.png",
                'professional_bio' => "Experienced sole trader profile {$i}",
                'public_liability_insurer' => 'Demo Insurance',
                'policy_number' => "PL-{$i}",
                'policy_expiry_date' => now()->addMonths(rand(1, 24))->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    private function seedSocialAccounts(): void
    {
        $users = DB::table('users')->pluck('id');
        $providers = ['google', 'facebook', 'apple'];
        $existing = DB::table('social_accounts')->count();

        for ($i = $existing + 1; $i <= self::MIN_COUNT; $i++) {
            DB::table('social_accounts')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $users->random(),
                'provider' => $providers[$i % count($providers)],
                'provider_user_id' => "provider_user_{$i}",
                'provider_email' => "social{$i}@example.com",
                'provider_avatar' => "https://example.com/avatar/{$i}.png",
                'provider_access_token' => Str::random(40),
                'provider_refresh_token' => Str::random(40),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedServiceAttributeDefinitions(): void
    {
        $services = DB::table('services')->pluck('id');
        $groups = DB::table('service_groups')->pluck('id');
        $dataTypes = ['enum', 'number', 'text', 'boolean'];

        $this->fillByPairs(
            'service_attribute_definitions',
            self::MIN_COUNT,
            function () use ($services, $groups, $dataTypes): array {
                return [
                    'service_id' => $services->random(),
                    'service_group_id' => $groups->random(),
                    'attribute_key' => 'attr_' . Str::lower(Str::random(6)),
                    'label' => 'Attribute ' . Str::upper(Str::random(3)),
                    'data_type' => $dataTypes[array_rand($dataTypes)],
                ];
            },
            fn (array $pair): bool => DB::table('service_attribute_definitions')
                ->where('service_id', $pair['service_id'])
                ->where('attribute_key', $pair['attribute_key'])
                ->exists(),
            function (array $pair): array {
                return array_merge($pair, [
                    'id' => (string) Str::uuid(),
                    'options_json' => $pair['data_type'] === 'enum' ? json_encode(['basic', 'standard', 'premium']) : null,
                    'unit' => $pair['data_type'] === 'number' ? 'sqm' : null,
                    'is_required' => rand(0, 1) === 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                ]);
            }
        );
    }

    private function seedServiceComplianceRequirements(): void
    {
        $serviceIds = DB::table('services')->pluck('id');
        $existing = DB::table('service_compliance_requirements')->count();

        for ($i = $existing + 1; $i <= self::MIN_COUNT; $i++) {
            DB::table('service_compliance_requirements')->insert([
                'id' => (string) Str::uuid(),
                'service_id' => $serviceIds->random(),
                'code' => "REQ-{$i}",
                'name' => "Requirement {$i}",
                'description' => "Compliance requirement {$i}",
                'is_required' => $i % 2 === 0,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    private function seedOrganizationServiceCompliances(): void
    {
        $orgIds = DB::table('organizations')->pluck('id');
        $requirements = DB::table('service_compliance_requirements')->select('id', 'service_id')->get();
        $reviewers = DB::table('users')->pluck('id');
        $statuses = ['pending', 'approved', 'rejected', 'expired'];

        $this->fillByPairs(
            'organization_service_compliances',
            self::MIN_COUNT,
            function () use ($orgIds, $requirements, $statuses, $reviewers): array {
                $requirement = $requirements->random();
                return [
                    'organization_id' => $orgIds->random(),
                    'service_id' => $requirement->service_id,
                    'requirement_id' => $requirement->id,
                    'status' => $statuses[array_rand($statuses)],
                    'reviewed_by' => $reviewers->random(),
                ];
            },
            fn (array $pair): bool => DB::table('organization_service_compliances')
                ->where('organization_id', $pair['organization_id'])
                ->where('service_id', $pair['service_id'])
                ->where('requirement_id', $pair['requirement_id'])
                ->exists(),
            fn (array $pair): array => array_merge($pair, [
                'id' => (string) Str::uuid(),
                'certificate_number' => 'CERT-' . rand(10000, 99999),
                'issued_at' => now()->subMonths(rand(1, 18))->toDateString(),
                'expires_at' => now()->addMonths(rand(1, 18))->toDateString(),
                'document_url' => 'https://example.com/documents/certificate.pdf',
                'reviewed_at' => now(),
                'review_notes' => 'Auto-seeded compliance record.',
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ])
        );
    }

    private function seedInquiries(): void
    {
        $organizationIds = DB::table('organizations')->pluck('id');
        $userIds = DB::table('users')->pluck('id');
        $staffIds = DB::table('users')
            ->whereIn('id', DB::table('user_roles')->whereIn('role_id', DB::table('roles')->whereIn('name', ['admin', 'admin_staff'])->pluck('id'))->pluck('user_id'))
            ->pluck('id');
        $propertyIds = DB::table('property_listings')->pluck('id');
        $existing = DB::table('inquiries')->count();
        $statuses = ['new', 'open', 'closed'];

        for ($i = $existing + 1; $i <= self::MIN_COUNT; $i++) {
            DB::table('inquiries')->insert([
                'id' => (string) Str::uuid(),
                'organization_id' => $organizationIds->random(),
                'user_id' => $i % 4 === 0 ? null : $userIds->random(),
                'staff_id' => $staffIds->isNotEmpty() ? $staffIds->random() : null,
                'property_listing_id' => $i % 3 === 0 ? null : $propertyIds->random(),
                'subject' => "Inquiry Subject {$i}",
                'message' => "Inquiry message {$i}",
                'status' => $statuses[$i % count($statuses)],
                'seeker_name' => "Guest Seeker {$i}",
                'seeker_email' => "guest{$i}@example.com",
                'seeker_phone' => '+614' . str_pad((string) (50000000 + $i), 8, '0', STR_PAD_LEFT),
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    private function seedReviews(): void
    {
        $userIds = DB::table('users')->pluck('id');
        $organizationIds = DB::table('organizations')->pluck('id');
        $propertyIds = DB::table('property_listings')->pluck('id');
        $existing = DB::table('reviews')->count();

        for ($i = $existing + 1; $i <= self::MIN_COUNT; $i++) {
            $forOrganization = $i % 2 === 0;

            DB::table('reviews')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $userIds->random(),
                'organization_id' => $forOrganization ? $organizationIds->random() : null,
                'property_listing_id' => $forOrganization ? null : $propertyIds->random(),
                'rating' => rand(1, 5),
                'comment' => "Review comment {$i}",
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    private function seedFavorites(): void
    {
        $userIds = DB::table('users')->pluck('id');
        $organizationIds = DB::table('organizations')->pluck('id');
        $propertyIds = DB::table('property_listings')->pluck('id');

        $this->fillByPairs(
            'favorites',
            self::MIN_COUNT,
            function () use ($userIds, $organizationIds, $propertyIds): array {
                $isOrg = rand(0, 1) === 1;
                return [
                    'user_id' => $userIds->random(),
                    'favoritable_type' => $isOrg ? 'App\\Models\\Organization' : 'App\\Models\\PropertyListing',
                    'favoritable_id' => $isOrg ? $organizationIds->random() : $propertyIds->random(),
                ];
            },
            fn (array $pair): bool => DB::table('favorites')
                ->where('user_id', $pair['user_id'])
                ->where('favoritable_type', $pair['favoritable_type'])
                ->where('favoritable_id', $pair['favoritable_id'])
                ->exists(),
            fn (array $pair): array => array_merge($pair, [
                'id' => (string) Str::uuid(),
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ])
        );
    }

    private function seedVerificationDocuments(): void
    {
        $organizationIds = DB::table('organizations')->pluck('id');
        $userIds = DB::table('users')->pluck('id');
        $reviewerIds = DB::table('users')->pluck('id');
        $existing = DB::table('verification_documents')->count();
        $statuses = ['Pending', 'Approved', 'Rejected'];

        for ($i = $existing + 1; $i <= self::MIN_COUNT; $i++) {
            DB::table('verification_documents')->insert([
                'id' => (string) Str::uuid(),
                'organization_id' => $i % 2 === 0 ? $organizationIds->random() : null,
                'entity_type' => $i % 2 === 0 ? 'organization' : 'user',
                'entity_id' => $i % 2 === 0 ? $organizationIds->random() : $userIds->random(),
                'doc_type' => $i % 2 === 0 ? 'abn' : 'identity',
                'file_url' => "https://example.com/docs/verification-{$i}.pdf",
                'status' => $statuses[$i % count($statuses)],
                'reviewed_by' => $reviewerIds->random(),
                'reviewed_at' => now(),
                'rejection_reason' => $i % 3 === 0 ? 'Sample rejection note' : null,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    private function seedVisitorLogs(): void
    {
        $viewerIds = DB::table('users')->pluck('id');
        $organizationIds = DB::table('organizations')->pluck('id');
        $propertyIds = DB::table('property_listings')->pluck('id');
        $existing = DB::table('visitor_logs')->count();

        for ($i = $existing + 1; $i <= self::MIN_COUNT; $i++) {
            DB::table('visitor_logs')->insert([
                'id' => (string) Str::uuid(),
                'viewer_id' => $i % 5 === 0 ? null : $viewerIds->random(),
                'organization_id' => $organizationIds->random(),
                'property_listing_id' => $i % 3 === 0 ? null : $propertyIds->random(),
                'ip_address' => '192.168.' . rand(1, 255) . '.' . rand(1, 255),
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    private function seedSystemIssues(): void
    {
        $reporterIds = DB::table('users')->pluck('id');
        $statuses = ['Open', 'In Progress', 'Resolved'];
        $severities = ['Low', 'Medium', 'High'];
        $existing = DB::table('system_issues')->count();

        for ($i = $existing + 1; $i <= self::MIN_COUNT; $i++) {
            DB::table('system_issues')->insert([
                'id' => (string) Str::uuid(),
                'reporter_id' => $reporterIds->random(),
                'description' => "System issue {$i}",
                'metadata' => json_encode(['source' => 'seed', 'index' => $i]),
                'status' => $statuses[$i % count($statuses)],
                'severity' => $severities[$i % count($severities)],
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    private function seedActivityLogs(): void
    {
        $causerIds = DB::table('users')->pluck('id');
        $subjectIds = DB::table('property_listings')->pluck('id');
        $existing = DB::table('activity_logs')->count();

        for ($i = $existing + 1; $i <= self::MIN_COUNT; $i++) {
            DB::table('activity_logs')->insert([
                'id' => (string) Str::uuid(),
                'causer_id' => $causerIds->random(),
                'subject_id' => $i % 4 === 0 ? null : $subjectIds->random(),
                'description' => "Activity log {$i}",
                'properties' => json_encode(['action' => 'update', 'index' => $i]),
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    private function seedApiTokens(): void
    {
        $userIds = DB::table('users')->pluck('id');
        $existing = DB::table('api_tokens')->count();

        for ($i = $existing + 1; $i <= self::MIN_COUNT; $i++) {
            DB::table('api_tokens')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $userIds->random(),
                'name' => "api-token-{$i}",
                'token_hash' => hash('sha256', "demo-token-{$i}"),
                'abilities' => json_encode(['admin']),
                'last_used_at' => now()->subDays(rand(0, 30)),
                'expires_at' => now()->addDays(60),
                'revoked_at' => null,
                'created_ip' => '10.0.0.' . rand(1, 240),
                'user_agent' => 'Seeder Demo Agent',
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    private function seedPersonalAccessTokens(): void
    {
        $userIds = DB::table('users')->pluck('id');
        $existing = DB::table('personal_access_tokens')->count();

        for ($i = $existing + 1; $i <= self::MIN_COUNT; $i++) {
            DB::table('personal_access_tokens')->insert([
                'tokenable_type' => 'App\\Models\\User',
                'tokenable_id' => $userIds->random(),
                'name' => "sanctum-token-{$i}",
                'token' => hash('sha256', "sanctum-demo-token-{$i}"),
                'abilities' => json_encode(['*']),
                'last_used_at' => now()->subDays(rand(0, 15)),
                'expires_at' => now()->addDays(30),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedMedia(): void
    {
        $existing = DB::table('media')->count();

        for ($i = $existing + 1; $i <= self::MIN_COUNT; $i++) {
            DB::table('media')->insert([
                'model_type' => 'App\\Models\\LegacyMediaOwner',
                'model_id' => rand(1, 999999),
                'uuid' => (string) Str::uuid(),
                'collection_name' => 'property-images',
                'name' => "property-image-{$i}",
                'file_name' => "property-image-{$i}.jpg",
                'mime_type' => 'image/jpeg',
                'disk' => 'public',
                'conversions_disk' => 'public',
                'size' => rand(50000, 2500000),
                'manipulations' => json_encode([]),
                'custom_properties' => json_encode(['seeded' => true]),
                'generated_conversions' => json_encode([]),
                'responsive_images' => json_encode([]),
                'order_column' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function fillByPairs(
        string $table,
        int $targetCount,
        callable $pairGenerator,
        callable $existsCallback,
        callable $payloadBuilder
    ): void {
        $attempts = 0;
        $maxAttempts = 2000;

        while (DB::table($table)->count() < $targetCount && $attempts < $maxAttempts) {
            $attempts++;
            $pair = $pairGenerator();

            if ($existsCallback($pair)) {
                continue;
            }

            DB::table($table)->insert($payloadBuilder($pair));
        }
    }
}
