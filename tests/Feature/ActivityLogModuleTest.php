<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityLogModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_filter_activity_logs_by_organization_and_paginate(): void
    {
        $this->seed();
        DB::table('activity_logs')->delete();

        $superAdmin = User::query()->where('email', 'superadmin@brisky.example')->firstOrFail();
        $admin = User::query()->where('email', 'harborview-realty@brisky.example')->firstOrFail();
        $otherOrganization = Organization::query()
            ->where('id', '!=', $admin->organization_id)
            ->firstOrFail();

        ActivityLog::query()->create([
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->id,
            'user_name' => $admin->name,
            'user_email' => $admin->email,
            'user_role' => 'admin',
            'action' => 'created',
            'module' => 'properties',
            'description' => 'Property created.',
            'method' => 'POST',
            'route' => '/api/admin/properties',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Pest',
            'old_values' => null,
            'new_values' => ['title' => 'First Property'],
            'metadata' => ['model_id' => 'prop-1'],
        ]);

        ActivityLog::query()->create([
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->id,
            'user_name' => $admin->name,
            'user_email' => $admin->email,
            'user_role' => 'admin',
            'action' => 'updated',
            'module' => 'properties',
            'description' => 'Property updated.',
            'method' => 'PUT',
            'route' => '/api/admin/properties/prop-1',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Pest',
            'old_values' => ['title' => 'Old Property'],
            'new_values' => ['title' => 'Updated Property'],
            'metadata' => ['model_id' => 'prop-1'],
        ]);

        ActivityLog::query()->create([
            'organization_id' => $otherOrganization->id,
            'user_id' => $superAdmin->id,
            'user_name' => $superAdmin->name,
            'user_email' => $superAdmin->email,
            'user_role' => 'super_admin',
            'action' => 'deleted',
            'module' => 'orders',
            'description' => 'Order deleted.',
            'method' => 'DELETE',
            'route' => '/api/super-admin/orders/order-1',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Pest',
            'old_values' => ['order_number' => 'ORD-1'],
            'new_values' => null,
            'metadata' => ['model_id' => 'order-1'],
        ]);

        Sanctum::actingAs($superAdmin, ['super_admin']);

        $response = $this->getJson('/api/super-admin/activity-logs?per_page=1&page=2&filter[organization_id]=' . $admin->organization_id);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonPath('meta.pagination.last_page', 2)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.organization_id', $admin->organization_id);
    }

    public function test_admin_is_scoped_to_own_organization_on_list_and_detail(): void
    {
        $this->seed();
        DB::table('activity_logs')->delete();

        $admin = User::query()->where('email', 'harborview-realty@brisky.example')->firstOrFail();
        $otherOrganization = Organization::query()
            ->where('id', '!=', $admin->organization_id)
            ->firstOrFail();

        $ownLog = ActivityLog::query()->create([
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->id,
            'user_name' => $admin->name,
            'user_email' => $admin->email,
            'user_role' => 'admin',
            'action' => 'login',
            'module' => 'auth',
            'description' => 'Admin logged in.',
            'method' => 'POST',
            'route' => '/api/admin/auth/login',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Pest',
            'old_values' => null,
            'new_values' => ['user_email' => $admin->email],
            'metadata' => ['portal' => 'admin'],
        ]);

        $otherLog = ActivityLog::query()->create([
            'organization_id' => $otherOrganization->id,
            'user_id' => $admin->id,
            'user_name' => $admin->name,
            'user_email' => $admin->email,
            'user_role' => 'admin',
            'action' => 'login',
            'module' => 'auth',
            'description' => 'Admin logged in elsewhere.',
            'method' => 'POST',
            'route' => '/api/admin/auth/login',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Pest',
            'old_values' => null,
            'new_values' => ['user_email' => $admin->email],
            'metadata' => ['portal' => 'admin'],
        ]);

        Sanctum::actingAs($admin, ['admin']);

        $indexResponse = $this->getJson('/api/admin/activity-logs');
        $indexResponse->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', $ownLog->id);

        $this->getJson("/api/admin/activity-logs/{$otherLog->id}")
            ->assertForbidden();
    }

    public function test_activity_log_service_redacts_sensitive_values(): void
    {
        $this->seed();
        DB::table('activity_logs')->delete();

        $superAdmin = User::query()->where('email', 'superadmin@brisky.example')->firstOrFail();
        Sanctum::actingAs($superAdmin, ['super_admin']);

        $request = request();
        $request->setUserResolver(fn () => $superAdmin);
        $request->headers->set('User-Agent', 'Pest Browser');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $this->app->instance('request', $request);

        app(ActivityLogService::class)->log(
            'updated',
            'settings',
            'Settings updated.',
            [
                'password' => 'secret',
                'api_key' => 'abc123',
                'nested' => [
                    'token' => 'hidden',
                    'safe' => 'kept',
                ],
            ],
            [
                'password' => 'old-secret',
                'safe' => 'old-value',
            ],
            [
                'password' => 'new-secret',
                'safe' => 'new-value',
            ]
        );

        $log = ActivityLog::query()->firstOrFail();

        $this->assertArrayNotHasKey('password', $log->metadata ?? []);
        $this->assertArrayNotHasKey('api_key', $log->metadata ?? []);
        $this->assertArrayNotHasKey('token', $log->metadata['nested'] ?? []);
        $this->assertArrayNotHasKey('password', $log->old_values ?? []);
        $this->assertArrayNotHasKey('password', $log->new_values ?? []);
        $this->assertSame('old-value', $log->old_values['safe'] ?? null);
        $this->assertSame('new-value', $log->new_values['safe'] ?? null);
    }
}
