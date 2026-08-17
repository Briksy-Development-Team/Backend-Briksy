<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityLogsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_read_all_activity_for_their_organization(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'harborview-realty@brisky.example')->firstOrFail();

        ActivityLog::query()->create([
            'causer_id' => $admin->id,
            'organization_id' => $admin->organization_id,
            'user_id' => null,
            'user_name' => 'System Process',
            'user_email' => null,
            'user_role' => 'system',
            'action' => 'property.update',
            'module' => 'property',
            'description' => 'System activity for the organization.',
        ]);

        Sanctum::actingAs($admin, ['admin']);

        $this->getJson('/api/admin/activity-logs')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1);
    }

    public function test_super_admin_can_read_platform_and_organization_activity(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'harborview-realty@brisky.example')->firstOrFail();
        $superAdmin = User::query()->where('email', 'superadmin@brisky.example')->firstOrFail();

        ActivityLog::query()->create([
            'causer_id' => $admin->id,
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->id,
            'user_name' => $admin->name,
            'user_email' => $admin->email,
            'user_role' => 'admin',
            'action' => 'property.create',
            'module' => 'property',
            'description' => 'Organization activity.',
        ]);
        ActivityLog::query()->create([
            'causer_id' => $superAdmin->id,
            'organization_id' => null,
            'user_id' => $superAdmin->id,
            'user_name' => $superAdmin->name,
            'user_email' => $superAdmin->email,
            'user_role' => 'super_admin',
            'action' => 'settings.update',
            'module' => 'settings',
            'description' => 'Platform activity.',
        ]);

        Sanctum::actingAs($superAdmin, ['super_admin']);

        $this->getJson('/api/super-admin/activity-logs')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 2);
    }
}
