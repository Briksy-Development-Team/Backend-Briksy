<?php

use App\Models\DynamicIdSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
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
            $setting = DynamicIdSetting::withTrashed()
                ->where('entity_type', $default['entity_type'])
                ->first();

            if (!$setting) {
                DynamicIdSetting::query()->create([
                    'entity_type' => $default['entity_type'],
                    'prefix' => $default['prefix'],
                    'separator' => '-',
                    'include_year' => false,
                    'include_month' => false,
                    'number_padding' => $default['number_padding'],
                    'starting_number' => $default['starting_number'],
                    'current_number' => 0,
                    'reset_frequency' => 'none',
                    'last_reset_at' => null,
                    'is_active' => true,
                ]);

                continue;
            }

            if ($setting->trashed()) {
                $setting->restore();
            }
        }
    }

    public function down(): void
    {
        // Intentionally left blank. Default settings may be customized in production.
    }
};
