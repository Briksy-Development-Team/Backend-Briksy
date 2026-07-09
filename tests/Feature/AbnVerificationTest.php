<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AbnVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_abn_verification_endpoint_returns_flat_verified_payload(): void
    {
        Http::fake([
            '*' => Http::response($this->successfulSoapResponse(), 200),
        ]);

        config()->set('services.abn_lookup.guid', 'test-guid');

        $response = $this->postJson('/api/auth/verify-abn', [
            'abn' => '51824753556',
            'business_type' => 'company',
        ]);

        $response->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('abn', '51824753556')
            ->assertJsonPath('entityName', 'Briksy Pty Ltd')
            ->assertJsonPath('entityType', 'Australian Private Company')
            ->assertJsonPath('gstRegistered', true)
            ->assertJsonPath('state', 'VIC')
            ->assertJsonPath('postcode', '3000')
            ->assertJsonPath('status', 'Active')
            ->assertJsonMissingPath('data');
    }

    public function test_abn_verification_endpoint_handles_business_entity_202001_payload(): void
    {
        Http::fake([
            '*' => Http::response($this->successfulSoapResponse202001(), 200),
        ]);

        config()->set('services.abn_lookup.guid', 'test-guid');

        $response = $this->postJson('/api/auth/verify-abn', [
            'abn' => '26008672179',
            'business_type' => 'company',
        ]);

        $response->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('abn', '26008672179')
            ->assertJsonPath('entityName', 'BUNNINGS GROUP LIMITED')
            ->assertJsonPath('entityType', 'Australian Public Company')
            ->assertJsonPath('gstRegistered', true)
            ->assertJsonPath('state', 'VIC')
            ->assertJsonPath('postcode', '3121')
            ->assertJsonPath('status', 'Active');
    }

    public function test_abn_verification_endpoint_rejects_business_type_mismatch(): void
    {
        Http::fake([
            '*' => Http::response($this->successfulSoapResponse(), 200),
        ]);

        config()->set('services.abn_lookup.guid', 'test-guid');

        $response = $this->postJson('/api/auth/verify-abn', [
            'abn' => '51824753556',
            'business_type' => 'solo_trader',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('valid', false)
            ->assertJsonPath('message', 'This ABN belongs to an Organisation / Company. Please select Organisation or Company.');
    }

    public function test_admin_registration_verifies_abn_before_creating_account(): void
    {
        Http::fake([
            '*' => Http::response($this->successfulSoapResponse(), 200),
        ]);

        config()->set('services.abn_lookup.guid', 'test-guid');
        $this->seed(\Database\Seeders\DynamicIdSettingSeeder::class);

        OrganizationType::create([
            'name' => 'Property Management',
            'slug' => 'property-management',
        ]);

        $response = $this->postJson('/api/admin/auth/register', [
            'first' => 'Ava',
            'last' => 'Manager',
            'email' => 'ava@example.com',
            'business_name' => 'Briksy Pty Ltd',
            'trading_name' => 'Briksy',
            'business_type' => 'company',
            'abn_number' => '51824753556',
            'contact_phone' => '0400000000',
            'address' => '123 Collins St',
            'state' => 'VIC',
            'postcode' => '3000',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'ava@example.com');

        $this->assertDatabaseHas('organizations', [
            'abn' => '51824753556',
            'abn_verified' => true,
            'entity_name' => 'Briksy Pty Ltd',
            'entity_type' => 'Australian Private Company',
            'entity_status' => 'Active',
            'gst_registered' => true,
            'state' => 'VIC',
            'postcode' => '3000',
            'business_verification_status' => 'verified',
            'is_verified' => true,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'ava@example.com',
        ]);
    }

    public function test_admin_registration_blocks_inactive_abns(): void
    {
        Http::fake([
            '*' => Http::response($this->inactiveSoapResponse(), 200),
        ]);

        config()->set('services.abn_lookup.guid', 'test-guid');
        $this->seed(\Database\Seeders\DynamicIdSettingSeeder::class);

        OrganizationType::create([
            'name' => 'Property Management',
            'slug' => 'property-management',
        ]);

        $response = $this->postJson('/api/admin/auth/register', [
            'first' => 'Ava',
            'last' => 'Manager',
            'email' => 'ava-inactive@example.com',
            'business_name' => 'Inactive Pty Ltd',
            'business_type' => 'company',
            'abn_number' => '51824753556',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Invalid or inactive Australian Business Number.');

        $this->assertDatabaseMissing('users', [
            'email' => 'ava-inactive@example.com',
        ]);
    }

    private function successfulSoapResponse(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <SearchByABNv202001Response xmlns="http://abr.business.gov.au/ABRXMLSearch/">
      <ABRPayloadSearchResults>
        <request />
        <response>
          <usageStatement>ABN Lookup data is provided by the Australian Business Register.</usageStatement>
          <dateRegisterLastUpdated>2026-07-07</dateRegisterLastUpdated>
          <dateTimeRetrieved>2026-07-07T00:00:00</dateTimeRetrieved>
          <businessEntity>
            <recordLastUpdatedDate>2026-07-07</recordLastUpdatedDate>
            <ABN>
              <identifierValue>51824753556</identifierValue>
              <isCurrentIndicator>Y</isCurrentIndicator>
            </ABN>
            <entityStatus>
              <entityStatusCode>Active</entityStatusCode>
              <effectiveFrom>2010-01-01</effectiveFrom>
            </entityStatus>
            <entityType>
              <entityTypeCode>PRV</entityTypeCode>
              <entityDescription>Australian Private Company</entityDescription>
            </entityType>
            <goodsAndServicesTax>
              <effectiveFrom>2010-01-01</effectiveFrom>
            </goodsAndServicesTax>
            <mainName>
              <organisationName>Briksy Pty Ltd</organisationName>
            </mainName>
            <mainBusinessPhysicalAddress>
              <stateCode>VIC</stateCode>
              <postcode>3000</postcode>
            </mainBusinessPhysicalAddress>
            <businessName>
              <organisationName>Briksy Property</organisationName>
            </businessName>
            <ASICNumber>123456789</ASICNumber>
          </businessEntity>
        </response>
      </ABRPayloadSearchResults>
    </SearchByABNv202001Response>
  </soap:Body>
</soap:Envelope>
XML;
    }

    private function inactiveSoapResponse(): string
    {
        return str_replace(
            '<entityStatusCode>Active</entityStatusCode>',
            '<entityStatusCode>Cancelled</entityStatusCode>',
            $this->successfulSoapResponse()
        );
    }

    private function successfulSoapResponse202001(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <SearchByABNv202001Response xmlns="http://abr.business.gov.au/ABRXMLSearch/">
      <ABRPayloadSearchResults>
        <request />
        <response>
          <businessEntity202001>
            <ABN>
              <identifierValue>26008672179</identifierValue>
              <isCurrentIndicator>Y</isCurrentIndicator>
            </ABN>
            <entityStatus>
              <entityStatusCode>Active</entityStatusCode>
              <effectiveFrom>1999-11-01</effectiveFrom>
            </entityStatus>
            <entityType>
              <entityTypeCode>PUB</entityTypeCode>
              <entityDescription>Australian Public Company</entityDescription>
            </entityType>
            <goodsAndServicesTax>
              <effectiveFrom>2000-07-01</effectiveFrom>
            </goodsAndServicesTax>
            <mainName>
              <organisationName>BUNNINGS GROUP LIMITED</organisationName>
            </mainName>
            <mainBusinessPhysicalAddress>
              <stateCode>VIC</stateCode>
              <postcode>3121</postcode>
            </mainBusinessPhysicalAddress>
            <businessName>
              <organisationName>BUNNINGS WAREHOUSE</organisationName>
            </businessName>
            <ASICNumber>008672179</ASICNumber>
          </businessEntity202001>
        </response>
      </ABRPayloadSearchResults>
    </SearchByABNv202001Response>
  </soap:Body>
</soap:Envelope>
XML;
    }
}
