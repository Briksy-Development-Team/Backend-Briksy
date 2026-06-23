<?php

namespace Tests\Feature;

use App\Models\NotificationPreference;
use App\Models\Organization;
use App\Models\PropertyListing;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationAndMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_property_map_is_scoped_to_own_organization(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'harborview-realty@brisky.example')->firstOrFail();
        Sanctum::actingAs($admin, ['admin']);

        $response = $this->getJson('/api/admin/properties/map');

        $response->assertOk()->assertJsonPath('success', true);

        foreach ($response->json('data') as $property) {
            $this->assertSame($admin->organization_id, $property['organization_id']);
        }
    }

    public function test_super_admin_property_map_can_include_multiple_organizations(): void
    {
        $this->seed();

        $superAdmin = User::query()->where('email', 'superadmin@brisky.example')->firstOrFail();
        Sanctum::actingAs($superAdmin, ['super_admin']);

        $response = $this->getJson('/api/super-admin/properties/map');

        $response->assertOk()->assertJsonPath('success', true);

        $organisationIds = collect($response->json('data'))->pluck('organization_id')->unique()->values();

        $this->assertGreaterThan(1, $organisationIds->count());
    }

    public function test_company_signup_creates_super_admin_notification(): void
    {
        $this->seed();

        $response = $this->postJson('/api/admin/auth/register', [
            'first' => 'Taylor',
            'last' => 'Builder',
            'email' => 'taylor-builder@example.test',
            'business_name' => 'Taylor Build Co',
            'trading_name' => 'Taylor Build',
            'business_type' => 'company',
            'abn_number' => $this->generateValidAbn(),
            'contact_phone' => '+61 400 222 333',
            'address' => '12 Sample Street, Sydney NSW 2000',
            'state' => 'NSW',
            'postcode' => '2000',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated();

        $superAdmin = User::query()->where('email', 'superadmin@brisky.example')->firstOrFail();
        $notification = DB::table('notifications')
            ->where('notifiable_id', $superAdmin->id)
            ->latest()
            ->first();

        $this->assertNotNull($notification);
        $payload = json_decode($notification->data, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('company_signup', $payload['type']);
    }

    public function test_notification_preferences_disable_email_channel(): void
    {
        $this->seed();

        Notification::fake();

        $user = User::query()->where('email', 'superadmin@brisky.example')->firstOrFail();
        NotificationPreference::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['in_app_enabled' => true, 'email_enabled' => false, 'type_preferences' => []]
        );

        app(NotificationService::class)->notifyUser(
            $user,
            app(NotificationService::class)->buildPayload(
                'system_setting_changed',
                'Settings updated',
                'Settings changed for your account.',
                'settings',
                'settings',
                '/super-admin/settings',
                'normal',
                null,
                null
            ),
            'Settings updated',
            'View settings'
        );

        Notification::assertSentTo(
            $user,
            \App\Notifications\PlatformNotification::class,
            function (\App\Notifications\PlatformNotification $notification, array $channels): bool {
                return in_array('database', $channels, true) && !in_array('mail', $channels, true);
            }
        );
    }

    private function generateValidAbn(): string
    {
        $prefix = '518247535';

        for ($suffix = 0; $suffix < 100; $suffix++) {
            $candidate = $prefix . str_pad((string) $suffix, 2, '0', STR_PAD_LEFT);

            if ($this->isValidAbn($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Unable to generate a valid ABN for tests.');
    }

    private function isValidAbn(string $abn): bool
    {
        if (!preg_match('/^\d{11}$/', $abn)) {
            return false;
        }

        $digits = array_map('intval', str_split($abn));
        $digits[0] -= 1;
        $weights = [10, 1, 3, 5, 7, 9, 11, 13, 15, 17, 19];
        $sum = 0;

        foreach ($digits as $index => $digit) {
            $sum += $digit * $weights[$index];
        }

        return $sum % 89 === 0;
    }
}
