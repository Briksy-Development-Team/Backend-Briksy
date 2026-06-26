<?php

namespace Database\Seeders;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Database\Seeder;

class NotificationPreferenceSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = app(NotificationService::class)->notificationPreferenceDefaults();

        User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['super_admin', 'admin', 'admin_staff']))
            ->chunkById(100, function ($users) use ($defaults): void {
                foreach ($users as $user) {
                    NotificationPreference::withTrashed()->updateOrCreate(
                        ['user_id' => $user->id],
                        [
                            'in_app_enabled' => $defaults['in_app_enabled'],
                            'email_enabled' => $defaults['email_enabled'],
                            'type_preferences' => $defaults['type_preferences'],
                            'deleted_at' => null,
                        ]
                    );
                }
            });
    }
}
