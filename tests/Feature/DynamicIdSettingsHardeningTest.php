<?php

namespace Tests\Feature;

use App\Exceptions\GeneratedIdImmutableException;
use App\Models\ActivityLog;
use App\Models\DynamicIdSetting;
use App\Models\Organization;
use App\Models\OrganizationType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DynamicIdSettingsHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            \Database\Seeders\RoleSeeder::class,
            \Database\Seeders\PermissionSeeder::class,
            \Database\Seeders\OrganizationTypeSeeder::class,
            \Database\Seeders\DynamicIdSettingSeeder::class,
            \Database\Seeders\SuperAdminSeeder::class,
        ]);
    }

    public function test_generated_ids_are_immutable_after_creation(): void
    {
        $type = OrganizationType::query()->firstOrFail();

        $organization = Organization::create([
            'plan_id' => null,
            'type_id' => $type->id,
            'name' => 'Immutable Org',
            'slug' => 'immutable-org',
            'abn' => '81111111111',
            'contact_email' => 'immutable@example.com',
        ]);

        $this->expectException(GeneratedIdImmutableException::class);

        $organization->fill([
            'generated_id' => 'COM-999999',
        ]);
        $organization->save();
    }

    public function test_soft_deleted_organizations_do_not_reuse_generated_ids(): void
    {
        $type = OrganizationType::query()->firstOrFail();

        $first = Organization::create([
            'plan_id' => null,
            'type_id' => $type->id,
            'name' => 'Soft Delete One',
            'slug' => 'soft-delete-one',
            'abn' => '82111111111',
            'contact_email' => 'soft1@example.com',
        ]);

        $first->delete();

        $second = Organization::create([
            'plan_id' => null,
            'type_id' => $type->id,
            'name' => 'Soft Delete Two',
            'slug' => 'soft-delete-two',
            'abn' => '83111111111',
            'contact_email' => 'soft2@example.com',
        ]);

        $this->assertSame('COM-000001', $first->generated_id);
        $this->assertSame('COM-000002', $second->generated_id);
    }

    public function test_dynamic_id_counter_reset_requires_confirmation(): void
    {
        $superAdmin = User::query()
            ->where('email', 'superadmin@brisky.example')
            ->firstOrFail();

        Sanctum::actingAs($superAdmin);

        $setting = DynamicIdSetting::query()
            ->where('entity_type', 'organizations')
            ->firstOrFail();

        $setting->forceFill([
            'current_number' => 25,
        ])->saveQuietly();

        $this->putJson("/api/super-admin/dynamic-id-settings/{$setting->id}", [
            'current_number' => 1,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['confirm_counter_reset']);

        $this->putJson("/api/super-admin/dynamic-id-settings/{$setting->id}", [
            'prefix' => 'ORG',
            'current_number' => 1,
        ])->assertOk()
            ->assertJsonPath('data.prefix', 'ORG')
            ->assertJsonPath('data.current_number', 1);

        $this->putJson("/api/super-admin/dynamic-id-settings/{$setting->id}", [
            'current_number' => 1,
            'confirm_counter_reset' => true,
        ])->assertOk()
            ->assertJsonPath('data.current_number', 1);

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'dynamic_id_settings',
            'action' => 'updated',
        ]);

        $this->assertDatabaseHas('dynamic_id_settings', [
            'id' => $setting->id,
            'current_number' => 1,
        ]);
    }
}
