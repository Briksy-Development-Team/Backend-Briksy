<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class BusinessModuleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_signup_creates_verified_business_profile(): void
    {
        $this->seed();
        config()->set('services.abn_lookup.guid', 'test-guid');
        Http::fake([
            '*' => Http::response($this->successfulSoapResponse('Australian Private Company'), 200),
        ]);

        $abn = $this->generateValidAbn();

        $response = $this->postJson('/api/admin/auth/register', [
            'first' => 'Jamie',
            'last' => 'Tester',
            'email' => 'jamie-company@example.com',
            'business_name' => 'Jamie Property Co',
            'trading_name' => 'Jamie Co',
            'business_type' => 'company',
            'abn_number' => $abn,
            'contact_phone' => '+61 400 111 222',
            'address' => '12 Example Street, Sydney NSW 2000',
            'state' => 'NSW',
            'postcode' => '2000',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $organizationId = $response->json('data.user.organization_id');

        $this->assertNotEmpty($organizationId);
        $this->assertDatabaseHas('organizations', [
            'id' => $organizationId,
            'business_type' => 'company',
            'business_verification_status' => 'verified',
            'abn_verified' => true,
            'abn' => $abn,
        ]);
    }

    public function test_company_admin_sees_property_module_but_not_service_module_by_default(): void
    {
        $admin = $this->createTenantAdmin('company');
        Sanctum::actingAs($admin, ['admin']);

        $response = $this->getJson('/api/me/permissions');

        $response->assertOk();

        $this->assertContains('property_management', $response->json('data.enabled_modules'));
        $this->assertNotContains('service_management', $response->json('data.enabled_modules'));
    }

    public function test_solo_trader_admin_sees_service_module_but_not_property_module_by_default(): void
    {
        $admin = $this->createTenantAdmin('solo_trader');
        Sanctum::actingAs($admin, ['admin']);

        $response = $this->getJson('/api/me/permissions');

        $response->assertOk();

        $this->assertContains('service_management', $response->json('data.enabled_modules'));
        $this->assertNotContains('property_management', $response->json('data.enabled_modules'));
    }

    public function test_company_admin_cannot_access_service_api_without_override(): void
    {
        $admin = $this->createTenantAdmin('company');
        Sanctum::actingAs($admin, ['admin']);

        $this->getJson('/api/admin/services')->assertForbidden();
    }

    public function test_solo_trader_admin_can_access_service_api(): void
    {
        $admin = $this->createTenantAdmin('solo_trader');
        Sanctum::actingAs($admin, ['admin']);

        $this->getJson('/api/admin/services')->assertOk();
    }

    private function createTenantAdmin(string $businessType): User
    {
        $this->seed();

        $organizationTypeSlug = $businessType === 'solo_trader' ? 'solo-traders' : 'property-management';
        $organizationType = OrganizationType::query()->where('slug', $organizationTypeSlug)->firstOrFail();
        $adminRole = Role::query()->where('name', 'admin')->firstOrFail();

        $organization = Organization::create([
            'name' => Str::title(str_replace('_', ' ', $businessType)) . ' Business',
            'trading_name' => null,
            'contact_email' => $businessType . '@example.test',
            'contact_phone' => null,
            'abn' => $this->generateValidAbn(),
            'business_type' => $businessType,
            'business_verification_status' => 'verified',
            'address' => '1 Test Street',
            'state' => 'NSW',
            'postcode' => '2000',
            'plan_id' => null,
            'type_id' => $organizationType->id,
            'ranking_priority' => 1,
            'avg_org_rating' => 0,
            'slug' => Str::slug($businessType . '-' . Str::random(6)),
            'stripe_customer_id' => null,
            'is_verified' => true,
            'abn_verified' => true,
            'abn_verified_at' => now(),
            'entity_name' => Str::title(str_replace('_', ' ', $businessType)) . ' Business',
            'entity_type' => $businessType === 'solo_trader' ? 'Individual/Sole Trader' : 'Australian Private Company',
            'entity_status' => 'Active',
            'gst_registered' => true,
            'abn_effective_from' => now()->toDateString(),
            'trial_started_at' => now(),
            'trial_ends_at' => now()->addDays(15),
            'subscription_status' => 'trialing',
        ]);

        $user = User::create([
            'name' => 'Business Admin',
            'email' => $businessType . '-admin@example.test',
            'password_hash' => 'Password123!',
            'organization_id' => $organization->id,
            'id_verified' => false,
        ]);

        $user->roles()->syncWithoutDetaching([
            $adminRole->id => [
                'id' => (string) Str::uuid(),
                'organization_id' => $organization->id,
            ],
        ]);

        return $user;
    }

    private function generateValidAbn(): string
    {
        $prefix = '518247535';

        for ($suffix = 0; $suffix < 100; $suffix++) {
            $candidate = $prefix . str_pad((string) $suffix, 2, '0', STR_PAD_LEFT);

            if ($this->isValidAbn($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Unable to generate a valid ABN for tests.');
    }

    private function isValidAbn(string $abn): bool
    {
        if (!preg_match('/^\d{11}$/', $abn)) {
            return false;
        }

        $digits = array_map('intval', str_split($abn));
        $digits[0] -= 1;
        $weights = [10, 1, 3, 5, 7, 9, 11, 13, 15, 17, 19];
        $sum = 0;

        foreach ($digits as $index => $digit) {
            $sum += $digit * $weights[$index];
        }

        return $sum % 89 === 0;
    }

    private function successfulSoapResponse(string $entityDescription): string
    {
        return str_replace(
            [
                '<entityDescription>Australian Private Company</entityDescription>',
            ],
            [
                "<entityDescription>{$entityDescription}</entityDescription>",
            ],
            <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <SearchByABNv202001Response xmlns="http://abr.business.gov.au/ABRXMLSearch/">
      <ABRPayloadSearchResults>
        <request />
        <response>
          <businessEntity>
            <ABN><identifierValue>51824753556</identifierValue></ABN>
            <entityStatus><entityStatusCode>Active</entityStatusCode><effectiveFrom>2010-01-01</effectiveFrom></entityStatus>
            <entityType><entityTypeCode>PRV</entityTypeCode><entityDescription>Australian Private Company</entityDescription></entityType>
            <goodsAndServicesTax><effectiveFrom>2010-01-01</effectiveFrom></goodsAndServicesTax>
            <mainName><organisationName>Jamie Property Co</organisationName></mainName>
            <mainBusinessPhysicalAddress><stateCode>NSW</stateCode><postcode>2000</postcode></mainBusinessPhysicalAddress>
          </businessEntity>
        </response>
      </ABRPayloadSearchResults>
    </SearchByABNv202001Response>
  </soap:Body>
</soap:Envelope>
XML
        );
    }
}
