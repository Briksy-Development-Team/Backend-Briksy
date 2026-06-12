<?php

namespace Database\Seeders;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            OrganizationTypeSeeder::class,
            ServiceSeeder::class,
            ServiceGroupSeeder::class,
            OrganizationSeeder::class,
            SubscriptionPlanSeeder::class,
            UserSeeder::class,
            SuperAdminSeeder::class,
            CommerceModuleSeeder::class,
            PropertyListingSeeder::class,
            PropertyListingFeatureSeeder::class,
        ]);
    }
}
