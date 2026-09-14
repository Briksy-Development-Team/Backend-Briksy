<?php

namespace Tests\Feature;

use App\Models\PropertyListing;
use App\Models\User;
use App\Support\Properties\PropertyWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PropertyWorkflowVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_property_returns_to_public_visibility_after_reapproval(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'harborview-realty@brisky.example')->firstOrFail();
        $superAdmin = User::query()->where('email', 'superadmin@brisky.example')->firstOrFail();
        $property = PropertyListing::query()
            ->where('org_id', $admin->organization_id)
            ->where('status', PropertyWorkflow::STATUS_PUBLISHED)
            ->firstOrFail();
        $newPrice = 987654.32;

        $this->getJson('/api/seeker/properties/' . $property->generated_id)
            ->assertOk()
            ->assertJsonPath('data.status', PropertyWorkflow::STATUS_PUBLISHED);

        Sanctum::actingAs($admin, ['admin']);
        $this->putJson('/api/admin/properties/' . $property->generated_id, [
            'price' => $newPrice,
        ])->assertOk();

        $property->refresh();
        $this->assertSame(PropertyWorkflow::STATUS_PENDING_REVIEW, $property->status);
        $this->assertNotNull($property->published_at);
        $this->getJson('/api/seeker/properties/' . $property->generated_id)->assertNotFound();

        Sanctum::actingAs($superAdmin, ['super_admin']);
        $this->patchJson('/api/super-admin/properties/' . $property->generated_id . '/approve')
            ->assertOk()
            ->assertJsonPath('data.status', PropertyWorkflow::STATUS_PUBLISHED);

        $property->refresh();
        $this->assertSame(PropertyWorkflow::STATUS_PUBLISHED, $property->status);
        $this->assertNotNull($property->published_at);
        $this->assertTrue((bool) $property->location_verified);

        $this->getJson('/api/seeker/properties/' . $property->generated_id)
            ->assertOk()
            ->assertJsonPath('data.price', $newPrice);
    }
}
