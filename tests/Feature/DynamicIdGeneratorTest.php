<?php

namespace Tests\Feature;

use App\Exceptions\DynamicIdConfigurationNotFoundException;
use App\Models\DynamicIdSetting;
use App\Services\DynamicIdGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DynamicIdGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_sequential_ids_from_dynamic_configuration(): void
    {
        $this->seed(\Database\Seeders\DynamicIdSettingSeeder::class);

        $service = app(DynamicIdGeneratorService::class);

        $this->assertSame('COM-000001', $service->generate('organizations'));
        $this->assertSame('COM-000002', $service->generate('organizations'));
        $this->assertSame('COM-000003', $service->generate('organizations'));
    }

    public function test_prefix_and_padding_changes_are_honored_without_code_changes(): void
    {
        DynamicIdSetting::query()->updateOrCreate(
            ['entity_type' => 'organizations'],
            [
                'prefix' => 'ORG',
                'separator' => '-',
                'include_year' => false,
                'include_month' => false,
                'number_padding' => 4,
                'starting_number' => 1,
                'current_number' => 0,
                'reset_frequency' => 'none',
                'last_reset_at' => null,
                'is_active' => true,
            ]
        );

        $service = app(DynamicIdGeneratorService::class);

        $this->assertSame('ORG-0001', $service->generate('organizations'));
        $this->assertSame('ORG-0002', $service->generate('organizations'));
    }

    public function test_inquiry_reference_starts_after_the_highest_existing_reference(): void
    {
        $this->seed(\Database\Seeders\DynamicIdSettingSeeder::class);
        $typeId = (string) Str::uuid();
        $organizationId = (string) Str::uuid();

        DB::table('organization_types')->insert([
            'id' => $typeId,
            'name' => 'Test business',
            'slug' => 'test-business',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('organizations')->insert([
            'id' => $organizationId,
            'type_id' => $typeId,
            'name' => 'Test organisation',
            'slug' => 'test-organisation',
            'abn' => '12345678901',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('inquiries')->insert([
            'id' => (string) Str::uuid(),
            'reference_no' => 'INQ-000014',
            'organization_id' => $organizationId,
            'user_id' => null,
            'staff_id' => null,
            'subject' => 'Existing enquiry',
            'message' => 'Existing message',
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame('INQ-000015', app(DynamicIdGeneratorService::class)->generate('inquiries'));
    }

    public function test_missing_configuration_throws_a_clear_exception(): void
    {
        $this->expectException(DynamicIdConfigurationNotFoundException::class);
        $this->expectExceptionMessage('Dynamic ID configuration not found for module: Unknown Module.');

        app(DynamicIdGeneratorService::class)->generate('unknown_module');
    }
}
