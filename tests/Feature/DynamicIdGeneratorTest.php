<?php

namespace Tests\Feature;

use App\Exceptions\DynamicIdConfigurationNotFoundException;
use App\Models\DynamicIdSetting;
use App\Services\DynamicIdGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_missing_configuration_throws_a_clear_exception(): void
    {
        $this->expectException(DynamicIdConfigurationNotFoundException::class);
        $this->expectExceptionMessage('Dynamic ID configuration not found for module: Unknown Module.');

        app(DynamicIdGeneratorService::class)->generate('unknown_module');
    }
}
