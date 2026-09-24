<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceMedia;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServicePlanLimitEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_image_limit_rejects_files_before_storage(): void
    {
        $this->seed();
        Storage::fake('public');
        $admin = $this->configureTradesPlan(['Portfolio Photos' => [true, 6]]);
        Sanctum::actingAs($admin, ['admin']);

        $payload = [
            'name' => 'Landscapers',
            'category' => 'Landscapers',
            'description' => 'Image limit test',
            'is_active' => '1',
            'images' => array_map(
                fn (int $index) => UploadedFile::fake()->image("service-{$index}.jpg"),
                range(1, 6),
            ),
        ];

        $this->post('/api/admin/services', $payload)->assertCreated();
        $service = Service::query()->where('name', 'Landscapers')->latest()->firstOrFail();
        $this->assertSame(6, $service->media()->where('media_type', 'image')->count());

        $this->post('/api/admin/services/'.$service->generated_id, [
            '_method' => 'PUT',
            'name' => $service->name,
            'category' => $service->category,
            'is_active' => '1',
            'images' => [UploadedFile::fake()->image('service-7.jpg')],
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'PLAN_IMAGE_LIMIT_REACHED');

        $this->assertSame(6, ServiceMedia::query()->where('service_id', $service->id)->where('media_type', 'image')->count());
        Storage::disk('public')->assertMissing('services/'.$service->id.'/images/service-7.jpg');
    }

    public function test_active_service_limit_returns_business_error_without_request_exception(): void
    {
        $this->seed();
        $admin = $this->configureTradesPlan(['Active Services' => [true, 10]]);
        $organization = $admin->organization()->firstOrFail();

        $existingActiveCount = Service::query()->where('organization_id', $organization->id)->where('is_active', true)->count();
        for ($index = 1; $index <= (10 - $existingActiveCount); $index++) {
            Service::query()->create([
                'organization_id' => $organization->id,
                'type_id' => $organization->type_id,
                'name' => "Active Service {$index}",
                'title' => "Active Service {$index}",
                'category' => 'Landscapers',
                'slug' => "active-service-{$index}",
                'is_active' => true,
            ]);
        }

        $inactive = Service::query()->create([
            'organization_id' => $organization->id,
            'type_id' => $organization->type_id,
            'name' => 'Inactive Service',
            'title' => 'Inactive Service',
            'category' => 'Landscapers',
            'slug' => 'inactive-service',
            'is_active' => false,
        ]);

        Sanctum::actingAs($admin, ['admin']);
        $this->put('/api/admin/services/'.$inactive->generated_id, [
            'name' => $inactive->name,
            'category' => $inactive->category,
            'is_active' => true,
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'PLAN_ACTIVE_SERVICE_LIMIT_REACHED')
            ->assertJsonPath('message', 'Your Enterprise plan allows up to 10 active services. Deactivate another service or upgrade your plan.');

        $this->assertFalse((bool) $inactive->fresh()->is_active);
        $this->assertSame(10, Service::query()->where('organization_id', $organization->id)->where('is_active', true)->count());
    }

    private function configureTradesPlan(array $overrides): User
    {
        $admin = User::query()->where('email', 'trades@demo.briksy.com')->firstOrFail();
        $plan = SubscriptionPlan::query()->where('plan_family', 'trades_professional')->where('name', 'Enterprise')->firstOrFail();
        $features = collect($plan->features ?? [])->map(function (array $feature) use ($overrides): array {
            if (isset($overrides[$feature['name']])) {
                [$enabled, $value] = $overrides[$feature['name']];
                $feature['enabled'] = $enabled;
                $feature['value'] = $value;
            }

            return $feature;
        })->all();
        $plan->update(['features' => $features]);
        $admin->organization()->update(['plan_id' => $plan->id]);
        $admin->organization->currentSubscription()->update(['subscription_plan_id' => $plan->id, 'status' => 'active']);

        return $admin->fresh(['organization.currentSubscription.plan', 'organization.plan']);
    }
}
