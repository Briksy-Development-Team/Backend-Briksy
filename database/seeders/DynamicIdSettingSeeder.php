<?php

namespace Database\Seeders;

use App\Models\DynamicIdSetting;
use Illuminate\Database\Seeder;

class DynamicIdSettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['entity_type' => 'organizations', 'prefix' => 'COM', 'number_padding' => 6, 'starting_number' => 1],
            ['entity_type' => 'users', 'prefix' => 'USR', 'number_padding' => 6, 'starting_number' => 1],
            ['entity_type' => 'employees', 'prefix' => 'EMP', 'number_padding' => 6, 'starting_number' => 1],
            ['entity_type' => 'properties', 'prefix' => 'PRO', 'number_padding' => 6, 'starting_number' => 1],
            ['entity_type' => 'services', 'prefix' => 'SER', 'number_padding' => 6, 'starting_number' => 1],
            ['entity_type' => 'jobs', 'prefix' => 'JOB', 'number_padding' => 6, 'starting_number' => 1],
            ['entity_type' => 'bookings', 'prefix' => 'BKG', 'number_padding' => 6, 'starting_number' => 1],
            ['entity_type' => 'quotes', 'prefix' => 'QTE', 'number_padding' => 6, 'starting_number' => 1],
            ['entity_type' => 'invoices', 'prefix' => 'INV', 'number_padding' => 6, 'starting_number' => 1],
            ['entity_type' => 'referrals', 'prefix' => 'REF', 'number_padding' => 6, 'starting_number' => 1],
            ['entity_type' => 'orders', 'prefix' => 'ORD', 'number_padding' => 6, 'starting_number' => 1],
            ['entity_type' => 'inquiries', 'prefix' => 'INQ', 'number_padding' => 6, 'starting_number' => 1],
            ['entity_type' => 'plan_requests', 'prefix' => 'PRQ', 'number_padding' => 6, 'starting_number' => 1],
        ];

        foreach ($defaults as $default) {
            DynamicIdSetting::withTrashed()->updateOrCreate(
                ['entity_type' => $default['entity_type']],
                [
                    'prefix' => $default['prefix'],
                    'separator' => '-',
                    'include_year' => $default['include_year'] ?? false,
                    'include_month' => $default['include_month'] ?? false,
                    'number_padding' => $default['number_padding'] ?? 6,
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
