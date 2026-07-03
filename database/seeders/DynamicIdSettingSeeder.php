<?php

namespace Database\Seeders;

use App\Models\DynamicIdSetting;
use Illuminate\Database\Seeder;

class DynamicIdSettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['entity_type' => 'orders', 'prefix' => 'ORD', 'include_year' => false, 'include_month' => false, 'starting_number' => 1],
            ['entity_type' => 'inquiries', 'prefix' => 'INQ', 'include_year' => true, 'include_month' => false, 'starting_number' => 1],
            ['entity_type' => 'properties', 'prefix' => 'PROP', 'include_year' => false, 'include_month' => false, 'starting_number' => 1],
            ['entity_type' => 'services', 'prefix' => 'SRV', 'include_year' => false, 'include_month' => false, 'starting_number' => 1],
            ['entity_type' => 'organizations', 'prefix' => 'COM', 'include_year' => false, 'include_month' => false, 'starting_number' => 1],
            ['entity_type' => 'plan_requests', 'prefix' => 'PRQ', 'include_year' => true, 'include_month' => false, 'starting_number' => 1],
        ];

        foreach ($defaults as $default) {
            DynamicIdSetting::withTrashed()->updateOrCreate(
                ['entity_type' => $default['entity_type']],
                [
                    'prefix' => $default['prefix'],
                    'separator' => '-',
                    'include_year' => $default['include_year'],
                    'include_month' => $default['include_month'],
                    'number_padding' => 6,
                    'starting_number' => $default['starting_number'],
                    'current_number' => 0,
                    'reset_frequency' => 'none',
                    'last_reset_at' => null,
                    'is_active' => true,
                    'deleted_at' => null,
                ]
            );
        }
    }
}
