<?php

namespace Tests\Feature;

use App\Models\PropertyListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PropertyMediaUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_property_with_multiple_videos(): void
    {
        $this->seed();
        Storage::fake('public');

        $admin = User::query()->where('email', 'harborview-realty@brisky.example')->firstOrFail();
        Sanctum::actingAs($admin, ['admin']);

        $response = $this->post('/api/admin/properties', $this->propertyPayload([
            UploadedFile::fake()->create('tour-one.mp4', 1024, 'video/mp4'),
            UploadedFile::fake()->create('tour-two.mp4', 1024, 'video/mp4'),
        ]));

        $response->assertCreated();
        $property = PropertyListing::query()->where('title', 'Multiple Video Property')->firstOrFail();
        $this->assertSame(2, $property->media()->where('media_type', 'video')->count());
    }

    public function test_admin_can_edit_property_with_multiple_videos(): void
    {
        $this->seed();
        Storage::fake('public');

        $admin = User::query()->where('email', 'harborview-realty@brisky.example')->firstOrFail();
        $property = PropertyListing::query()->where('org_id', $admin->organization_id)->firstOrFail();
        $existingVideoCount = $property->media()->where('media_type', 'video')->count();
        Sanctum::actingAs($admin, ['admin']);

        $response = $this->post('/api/admin/properties/' . $property->generated_id, $this->propertyPayload([
            UploadedFile::fake()->create('edit-one.mp4', 1024, 'video/mp4'),
            UploadedFile::fake()->create('edit-two.mp4', 1024, 'video/mp4'),
        ]) + ['_method' => 'PUT']);

        $response->assertOk();
        $this->assertSame($existingVideoCount + 2, $property->fresh()->media()->where('media_type', 'video')->count());
    }

    private function propertyPayload(array $videos): array
    {
        return [
            'title' => 'Multiple Video Property',
            'address' => '1 Test Street, Sydney NSW 2000',
            'address_line_1' => '1 Test Street',
            'full_address' => '1 Test Street, Sydney NSW 2000',
            'suburb' => 'Sydney',
            'state' => 'NSW',
            'postcode' => '2000',
            'country' => 'Australia',
            'videos' => $videos,
        ];
    }
}
