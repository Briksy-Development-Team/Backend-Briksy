<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $notificationService = app(NotificationService::class);

        $superAdmin = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'super_admin'))
            ->first();

        if ($superAdmin) {
            $this->seedNotificationsForUser($superAdmin, [
                [
                    'payload' => $notificationService->buildPayload(
                        'company_signup',
                        'New company signup',
                        'A new company registered an admin account.',
                        User::class,
                        null,
                        '/super-admin/companies',
                        'high',
                        null,
                        null,
                        'company.view'
                    ),
                    'seed_key' => 'seed-super-admin-company-signup',
                    'read_at' => null,
                ],
                [
                    'payload' => $notificationService->buildPayload(
                        'plan_request_created',
                        'Plan request received',
                        'A new plan request needs review.',
                        User::class,
                        null,
                        '/super-admin/plan-requests',
                        'high',
                        null,
                        null,
                        'plan_request.view'
                    ),
                    'seed_key' => 'seed-super-admin-plan-request',
                    'read_at' => now()->subHours(3),
                ],
            ]);
        }

        User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'admin'))
            ->with('organization')
            ->chunkById(100, function ($admins) use ($notificationService): void {
                foreach ($admins as $admin) {
                    if (!$admin->organization_id) {
                        continue;
                    }

                    $this->seedNotificationsForUser($admin, [
                        [
                            'payload' => $notificationService->buildPayload(
                                'property_created',
                                'Property listing added',
                                sprintf('A new property was added for %s.', $admin->organization?->name ?? 'your organisation'),
                                User::class,
                                null,
                                '/admin/property-management',
                                'normal',
                                null,
                                $admin->organization_id,
                                'property.view'
                            ),
                            'seed_key' => 'seed-admin-' . $admin->id . '-property-created',
                            'read_at' => null,
                        ],
                        [
                            'payload' => $notificationService->buildPayload(
                                'property_location_missing',
                                'Property needs coordinates',
                                'One of your properties needs verified map coordinates.',
                                User::class,
                                null,
                                '/admin/property-management',
                                'high',
                                null,
                                $admin->organization_id,
                                'property.update'
                            ),
                            'seed_key' => 'seed-admin-' . $admin->id . '-property-location',
                            'read_at' => now()->subHours(5),
                        ],
                        [
                            'payload' => $notificationService->buildPayload(
                                'user_invited',
                                'Team member invited',
                                'A staff member was invited to your organisation.',
                                User::class,
                                null,
                                '/admin/users',
                                'normal',
                                null,
                                $admin->organization_id,
                                'user.view'
                            ),
                            'seed_key' => 'seed-admin-' . $admin->id . '-user-invited',
                            'read_at' => null,
                        ],
                        [
                            'payload' => $notificationService->buildPayload(
                                'order_created',
                                'New order created',
                                'A new order has been created for your organisation.',
                                User::class,
                                null,
                                '/admin/orders',
                                'normal',
                                null,
                                $admin->organization_id,
                                'order.view'
                            ),
                            'seed_key' => 'seed-admin-' . $admin->id . '-order-created',
                            'read_at' => now()->subDay(),
                        ],
                    ]);
                }
            });
    }

    /**
     * @param array<int, array{payload: array, seed_key: string, read_at: ?Carbon}> $items
     */
    private function seedNotificationsForUser(User $user, array $items): void
    {
        foreach ($items as $item) {
            $createdAt = $item['read_at']
                ? $item['read_at']->copy()->subMinutes(10)
                : now()->subMinutes(10 + (crc32($item['seed_key']) % 180));

            DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', $user->id)
                ->where('data->seed_key', $item['seed_key'])
                ->delete();

            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => \App\Notifications\PlatformNotification::class,
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => json_encode(array_merge($item['payload'], [
                    'seed_key' => $item['seed_key'],
                ]), JSON_THROW_ON_ERROR),
                'read_at' => $item['read_at']?->toDateTimeString(),
                'created_at' => $createdAt->toDateTimeString(),
                'updated_at' => $createdAt->toDateTimeString(),
            ]);
        }
    }
}
