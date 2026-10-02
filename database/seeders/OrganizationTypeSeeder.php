<?php

namespace Database\Seeders;

use App\Models\OrganizationType;
use Illuminate\Database\Seeder;

class OrganizationTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'real-estate' => ['name' => 'Real Estate', 'display_name' => 'Real Estate', 'module' => 'Real Estate', 'capability_profile' => 'real-estate-agency', 'sort_order' => 1],
            'buyers-agent' => ['name' => 'Buyer Agents', 'display_name' => 'Buyer Agents', 'module' => 'Agents', 'capability_profile' => 'buyers-agent', 'plan_family' => 'buyers_agent', 'is_active' => true, 'sort_order' => 1],
            'real-estate-agent' => ['name' => 'Real Estate Agents', 'display_name' => 'Real Estate Agents', 'module' => 'Agents', 'capability_profile' => 'real-estate-agent', 'plan_family' => 'buyers_agent', 'is_active' => true, 'sort_order' => 2],
            'builders' => ['name' => 'Builders', 'display_name' => 'Builders', 'module' => 'Builders', 'capability_profile' => 'builder', 'is_active' => true, 'sort_order' => 1],
            'trades-professionals' => ['name' => 'Trades & Professionals', 'display_name' => 'Trades & Professionals', 'module' => 'Trades & Professionals', 'capability_profile' => 'trades', 'is_active' => true, 'sort_order' => 1],
        ];

        foreach ($types as $slug => $definition) {
            $values = is_array($definition)
                ? $definition
                : ['name' => $definition];
            OrganizationType::withTrashed()->updateOrCreate(
                ['slug' => $slug],
                array_merge($values, ['deleted_at' => null])
            );
        }

        // Legacy registration flows still submit these slugs. Keep them as
        // compatibility aliases; seeded/demo categories use the four types
        // above exclusively.
        foreach (['property-management' => 'Legacy Property Management', 'solo-traders' => 'Legacy Solo Traders'] as $slug => $name) {
            OrganizationType::withTrashed()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'deleted_at' => null]
            );
        }
    }
}
