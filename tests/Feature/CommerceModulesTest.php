<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Organization;
use App\Models\PlanRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommerceModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_coupon_validation_works_for_seeded_coupon(): void
    {
        $this->seed();
        $superAdmin = User::query()->where('email', 'superadmin@brisky.example')->firstOrFail();
        Sanctum::actingAs($superAdmin, ['super_admin']);

        $response = $this->postJson('/api/super-admin/coupons/validate', [
            'code' => 'WELCOME10',
            'amount' => 1200,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.valid', true);
    }

    public function test_plan_request_approval_creates_order(): void
    {
        $this->seed();
        $superAdmin = User::query()->where('email', 'superadmin@brisky.example')->firstOrFail();
        Sanctum::actingAs($superAdmin, ['super_admin']);

        $organization = Organization::query()->where('slug', 'harborview-realty')->firstOrFail();
        $plan = $organization->plan;

        $create = $this->postJson('/api/super-admin/plan-requests', [
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'company_name' => $organization->name,
            'contact_name' => 'Admin User',
            'contact_email' => 'admin@example.com',
            'requested_plan_name' => $plan->name,
            'billing_cycle' => 'monthly',
            'message' => 'Please approve this plan request.',
        ]);

        $create->assertCreated();
        $planRequestId = $create->json('data.id');

        $this->postJson("/api/super-admin/plan-requests/{$planRequestId}/approve", [
            'admin_notes' => 'Approved.',
            'create_order' => true,
        ])->assertOk();

        $this->assertDatabaseHas('plan_requests', [
            'id' => $planRequestId,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('orders', [
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'payment_status' => 'paid',
            'order_status' => 'active',
        ]);
    }

    public function test_email_template_preview_renders_variables(): void
    {
        $this->seed();
        $superAdmin = User::query()->where('email', 'superadmin@brisky.example')->firstOrFail();
        Sanctum::actingAs($superAdmin, ['super_admin']);

        $templateId = \DB::table('email_templates')->where('key', 'welcome-email')->value('id');

        $response = $this->postJson("/api/super-admin/email-templates/{$templateId}/preview", [
            'variables' => [
                'name' => 'Jamie',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.subject', 'Welcome to Briksy, Jamie');
    }

    public function test_company_settings_update_is_scoped_to_admin_organization(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'harborview-realty@brisky.example')->firstOrFail();
        Sanctum::actingAs($admin, ['admin']);

        $response = $this->patchJson('/api/admin/settings', [
            'settings' => [
                [
                    'key' => 'company_name',
                    'value' => 'Harborview Realty Updated',
                    'type' => 'string',
                    'group' => 'profile',
                    'label' => 'Company Name',
                ],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('company_settings', [
            'organization_id' => $admin->organization_id,
            'key' => 'company_name',
            'value' => 'Harborview Realty Updated',
        ]);
    }

    public function test_admin_cannot_read_orders_from_another_company(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'harborview-realty@brisky.example')->firstOrFail();
        Sanctum::actingAs($admin, ['admin']);

        $otherOrder = Order::query()
            ->where('organization_id', '!=', $admin->organization_id)
            ->firstOrFail();

        $this->getJson("/api/admin/orders/{$otherOrder->id}")
            ->assertNotFound();
    }
}
