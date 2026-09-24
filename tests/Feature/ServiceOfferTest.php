<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServiceOfferTest extends TestCase
{
    use RefreshDatabase;

    public function test_trades_admin_can_manage_service_offers_when_plan_includes_promo_offers(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'trades@demo.briksy.com')->firstOrFail();
        $service = Service::query()->where('organization_id', $admin->organization_id)->firstOrFail();
        Sanctum::actingAs($admin, ['admin']);

        $response = $this->postJson('/api/admin/service-offers', [
            'service_id' => $service->id,
            'title' => 'Spring maintenance offer',
            'summary' => 'Save on a bundled service visit.',
            'is_active' => true,
        ]);

        $response->assertCreated()->assertJsonPath('data.service_id', $service->id);
        $this->getJson('/api/admin/service-offers')->assertOk()->assertJsonPath('data.0.title', 'Spring maintenance offer');
    }
}
