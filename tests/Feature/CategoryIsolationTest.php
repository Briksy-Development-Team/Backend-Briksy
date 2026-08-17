<?php

namespace Tests\Feature;

use App\Models\PropertyListing;
use App\Models\User;
use App\Support\Business\BusinessModuleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_demo_accounts_receive_category_specific_modules(): void
    {
        $this->seed();
        $resolver = app(BusinessModuleResolver::class);

        $realEstate = User::where('email', 'realestate@demo.briksy.com')->firstOrFail();
        $buyersAgent = User::where('email', 'buyersagent@demo.briksy.com')->firstOrFail();
        $builder = User::where('email', 'builder@demo.briksy.com')->firstOrFail();
        $trades = User::where('email', 'trades@demo.briksy.com')->firstOrFail();

        $this->assertContains('property_management', $resolver->resolve($realEstate));
        $this->assertNotContains('service_management', $resolver->resolve($realEstate));
        $this->assertNotContains('property_management', $resolver->resolve($buyersAgent));
        $this->assertContains('buyer_management', $resolver->resolve($buyersAgent));
        $this->assertNotContains('property_management', $resolver->resolve($builder));
        $this->assertContains('builder_management', $resolver->resolve($builder));
        $this->assertNotContains('property_management', $resolver->resolve($trades));
        $this->assertContains('service_management', $resolver->resolve($trades));
        $this->assertSame(0, PropertyListing::where('org_id', $trades->organization_id)->count());
    }

    public function test_buyer_and_builder_workflows_are_category_scoped(): void
    {
        $this->seed();
        $buyer = User::where('email', 'buyersagent@demo.briksy.com')->firstOrFail();
        $builder = User::where('email', 'builder@demo.briksy.com')->firstOrFail();

        Sanctum::actingAs($buyer, ['admin']);
        $this->getJson('/api/admin/buyer-briefs')->assertOk()->assertJsonPath('meta.pagination.total', 2);
        $this->getJson('/api/admin/builder-projects')->assertForbidden();

        Sanctum::actingAs($builder, ['admin']);
        $this->getJson('/api/admin/builder-projects')->assertOk()->assertJsonPath('meta.pagination.total', 2);
        $this->getJson('/api/admin/buyer-briefs')->assertForbidden();
    }

    public function test_trades_account_cannot_access_property_api(): void
    {
        $this->seed();
        $trades = User::where('email', 'trades@demo.briksy.com')->firstOrFail();
        Sanctum::actingAs($trades, ['admin']);

        $this->getJson('/api/admin/properties')
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden. Missing required business module.');
    }
}
