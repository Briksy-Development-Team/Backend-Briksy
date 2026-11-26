<?php

namespace Tests\Feature;

use App\Models\BuilderProject;
use App\Models\Inquiry;
use App\Models\Organization;
use App\Models\PropertyListing;
use App\Models\PropertyOffer;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Support\Properties\PropertyWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuperAdminListingCountFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Sanctum::actingAs(
            User::query()->where('email', 'superadmin@brisky.example')->firstOrFail(),
            ['super_admin'],
        );
    }

    public function test_property_status_totals_cover_all_pages_and_combine_with_search_and_organization_scope(): void
    {
        $property = PropertyListing::query()->whereHas('organization.organizationType', fn ($type) => $type->where('slug', 'real-estate'))->firstOrFail();
        $titles = ['Count Test Alpha', 'Count Test Beta', 'Other listing', 'Count Test Pending', 'Count Test Deleted'];
        $statuses = ['Published', 'Published', 'Published', 'Pending Review', 'Published'];
        foreach ($titles as $index => $title) {
            $listing = $property->replicate(['generated_id']);
            $listing->fill(['title' => $title, 'status' => $statuses[$index]]);
            $listing->save();
            if ($title === 'Count Test Deleted') {
                $listing->delete();
            }
        }

        $params = [
            'search' => 'Count Test',
            'filter' => ['status' => PropertyWorkflow::STATUS_PUBLISHED],
            'sort' => 'title',
            'direction' => 'asc',
        ];
        $path = "/api/super-admin/organizations/{$property->org_id}/properties";
        $first = $this->getJson($path.'?'.http_build_query([...$params, 'per_page' => 1]))
            ->assertOk()->assertJsonPath('meta.pagination.total', 2)->assertJsonPath('data.0.title', 'Count Test Alpha');
        $second = $this->getJson($path.'?'.http_build_query([...$params, 'per_page' => 1, 'page' => 2]))
            ->assertOk()->assertJsonPath('meta.pagination.total', 2)->assertJsonPath('data.0.title', 'Count Test Beta');
        $this->assertNotSame($first->json('data.0.id'), $second->json('data.0.id'));

        $ids = [$first->json('data.0.id'), $second->json('data.0.id')];
        sort($ids);
        foreach ($ids as $index => $id) {
            $query = http_build_query([...$params, 'sort' => 'status', 'per_page' => 1, 'page' => $index + 1]);
            $this->getJson($path.'?'.$query)
                ->assertOk()->assertJsonPath('data.0.id', $id);
        }

        $params['filter']['status'] = PropertyWorkflow::STATUS_REJECTED;
        $this->getJson($path.'?'.http_build_query($params))
            ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.pagination.total', 0);
        unset($params['filter']['status']);
        $this->getJson($path.'?'.http_build_query($params))
            ->assertOk()->assertJsonPath('meta.pagination.total', 3);
    }

    public function test_catalog_status_filters_run_before_pagination_and_preserve_the_legacy_category_response(): void
    {
        ServiceCategory::create(['name' => 'Count Test Active', 'slug' => 'count-test-active', 'is_active' => true, 'sort_order' => 10]);
        ServiceCategory::create(['name' => 'Count Test Inactive', 'slug' => 'count-test-inactive', 'is_active' => false, 'sort_order' => 20]);

        $this->getJson('/api/super-admin/service-categories?active_only=1')
            ->assertOk()->assertJsonMissing(['slug' => 'count-test-inactive'])->assertJsonMissingPath('meta');
        $this->getJson('/api/super-admin/service-categories?search=Count+Test&filter[is_active]=0&per_page=1')
            ->assertOk()->assertJsonPath('meta.pagination.total', 1)->assertJsonPath('data.0.slug', 'count-test-inactive');
        $this->getJson('/api/super-admin/service-categories?search=Count+Test&per_page=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.pagination.total', 2);
    }

    public function test_offers_search_related_property_with_activity_filter_and_soft_delete_scope(): void
    {
        $property = PropertyListing::query()->firstOrFail();
        $property->update(['title' => 'Count Test Related Property']);
        foreach ([true, false, false] as $index => $active) {
            $offer = PropertyOffer::create([
                'title' => "Listing promotion {$index}",
                'property_listing_id' => $property->id,
                'organization_id' => $property->org_id,
                'is_active' => $active,
            ]);
            if ($index === 2) {
                $offer->delete();
            }
        }

        $this->getJson('/api/super-admin/property-offers?search=Count+Test+Related+Property&filter[is_active]=0&per_page=1')
            ->assertOk()->assertJsonPath('meta.pagination.total', 1)->assertJsonPath('data.0.title', 'Listing promotion 1');
    }

    public function test_referring_companies_count_matches_search_and_excludes_deleted_referrals(): void
    {
        [$first, $second, $referred, $deleted, $unrelated] = Organization::query()->limit(5)->get()->all();
        $first->update(['name' => 'Count Test Alpha']);
        $second->update(['name' => 'Count Test Beta']);
        $referred->update(['referred_by_organization_id' => $first->id]);
        $deleted->update(['referred_by_organization_id' => $second->id]);
        $unrelated->update(['referred_by_organization_id' => $unrelated->id]);
        $deleted->delete();

        $response = $this->getJson('/api/super-admin/referrals?search=Count+Test&filter[with_referrals]=1&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.totals.organizations', 3)
            ->assertJsonPath('data.totals.with_referrals', 1)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.organizations.0.id', $first->id);

        $this->getJson('/api/super-admin/referrals?search=Count+Test')
            ->assertOk()->assertJsonPath('data.pagination.total', $response->json('data.totals.organizations'));
        $this->getJson('/api/super-admin/referrals?filter[with_referrals]=unexpected')->assertUnprocessable();
    }

    public function test_builder_project_count_filter_combines_status_organization_and_date(): void
    {
        [$organization, $other] = Organization::query()->limit(2)->get()->all();
        foreach ([$organization, $other] as $company) {
            BuilderProject::create(['organization_id' => $company->id, 'name' => 'Count Test Project', 'status' => 'Pending Review']);
            BuilderProject::create(['organization_id' => $company->id, 'name' => 'Count Test Published', 'status' => 'Published']);
        }
        $query = http_build_query([
            'search' => 'Count Test',
            'per_page' => 1,
            'filter' => ['status' => 'Pending Review', 'organization_id' => $organization->id, 'created_at' => now()->toDateString().'~'.now()->toDateString()],
        ]);

        $this->getJson('/api/super-admin/builder-projects?'.$query)->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.data.0.organization_id', $organization->id)
            ->assertJsonPath('data.data.0.status', 'Pending Review');
    }

    public function test_inquiry_status_combines_with_end_user_and_lead_source(): void
    {
        [$user, $otherUser] = User::query()->limit(2)->get()->all();
        foreach ([[$user, 'new', 'auction_registration'], [$otherUser, 'new', 'auction_registration'], [$user, 'closed', 'auction_registration'], [$user, 'new', 'direct']] as $index => [$author, $status, $source]) {
            Inquiry::create([
                'user_id' => $author->id,
                'reference_no' => "COUNT-TEST-{$index}",
                'subject' => 'Count Test Enquiry',
                'message' => 'Example',
                'status' => $status,
                'lead_source' => $source,
            ]);
        }
        $query = http_build_query([
            'search' => 'Count Test',
            'per_page' => 1,
            'filter' => ['status' => 'new', 'user_id' => $user->id, 'lead_source' => 'auction_registration'],
        ]);

        $this->getJson('/api/super-admin/inquiries?'.$query)->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.reference_no', 'COUNT-TEST-0');
    }

    public function test_unread_notification_count_uses_the_listing_search_and_recipient_scope(): void
    {
        $recipient = User::query()->where('email', 'superadmin@brisky.example')->firstOrFail();
        $other = User::query()->whereKeyNot($recipient->id)->firstOrFail();
        foreach ([[$recipient, 'Count Test', null], [$recipient, 'Count Test', now()], [$other, 'Count Test', null], [$recipient, 'Other', null]] as [$user, $title, $readAt]) {
            DatabaseNotification::create([
                'id' => (string) Str::uuid(),
                'type' => 'example',
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => ['title' => $title],
                'read_at' => $readAt,
            ]);
        }

        $this->getJson('/api/super-admin/notifications?search=Count+Test&filter[unread]=1&per_page=1')
            ->assertOk()->assertJsonPath('meta.pagination.total', 1)->assertJsonPath('data.0.title', 'Count Test');
        $this->getJson('/api/super-admin/notifications?search=Count+Test&per_page=1')
            ->assertOk()->assertJsonPath('meta.pagination.total', 2);
    }

    public function test_existing_list_endpoints_accept_their_count_filter_values(): void
    {
        $listings = [
            ['organizations', 'business_verification_status', ['verified', 'pending', 'rejected'], ['business_type' => 'organisation']],
            ['services', 'is_active', ['1', '0']],
            ['service-offers', 'is_active', ['1', '0']],
            ['addons', 'is_active', ['1', '0']],
            ['email-templates', 'is_active', ['1', '0']],
            ['organization-types', 'is_active', ['1', '0'], ['module' => 'Agents']],
            ['coupons', 'status', ['active', 'inactive', 'expired']],
            ['plan-requests', 'status', ['pending', 'approved', 'rejected', 'cancelled']],
            ['orders', 'order_status', ['draft', 'confirmed', 'active', 'expired', 'cancelled']],
            ['seekers', 'email_verified', ['1', '0']],
        ];

        foreach ($listings as [$path, $field, $values]) {
            foreach ($values as $value) {
                $query = http_build_query(['filter' => [$field => $value], 'per_page' => 1]);
                $response = $this->getJson("/api/super-admin/{$path}?{$query}")->assertOk();
                $total = $response->json('meta.pagination.total');
                $this->assertIsInt($total);
                $response->assertJsonCount(min($total, 1), 'data');
                foreach ($response->json('data') as $row) {
                    if ($field === 'email_verified') {
                        $this->assertSame($value === '1', $row['email_verified_at'] !== null);
                    } else {
                        $this->assertSame($value, (string) (is_bool($row[$field]) ? (int) $row[$field] : $row[$field]));
                    }
                }
            }
        }
    }

    public function test_existing_visible_sort_options_work_with_filtered_list_requests(): void
    {
        foreach ([
            ['staff', 'email_verified_at', []],
            ['organizations', 'pending_properties_count', ['business_verification_status' => 'verified']],
            ['plan-requests', 'requested_plan_name', ['status' => 'pending', 'billing_cycle' => 'monthly']],
        ] as [$resource, $sort, $filters]) {
            $query = http_build_query(['sort' => $sort, 'direction' => 'desc', 'filter' => $filters]);

            $this->getJson("/api/super-admin/{$resource}?{$query}")->assertOk();
        }
    }

}
