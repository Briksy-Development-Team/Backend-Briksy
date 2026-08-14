<?php

namespace Database\Seeders;

use App\Models\OrganizationType;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Services\DynamicIdGeneratorService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $generator = app(DynamicIdGeneratorService::class);
        $types = OrganizationType::all()->keyBy('slug');
        // Terms used here map to different UI/data concepts:
        // - business_type: top-level list split used by the Organizations page
        // - type_slug: one of the four seeded categories below
        // - plan_id: billing plan assigned after this seeder runs
        $organizations = $this->organizations();

        foreach ($organizations as $org) {
            $type = $types->get($org['type_slug']);
            if (!$type) {
                continue;
            }

            $existing = DB::table('organizations')->where('slug', $org['slug'])->first();
            $payload = [
                'plan_id' => null,
                'type_id' => $type->id,
                'generated_id' => $existing?->generated_id ?? $generator->generate('organizations'),
                'ranking_priority' => $org['ranking_priority'],
                'avg_org_rating' => $org['ranking_priority'] >= 3 ? 4.4 : 4.0,
                'name' => $org['name'],
                'slug' => $org['slug'],
                'business_type' => $org['business_type'],
                'business_verification_status' => $org['business_verification_status'],
                'abn' => $org['abn'],
                'acn' => $org['acn'],
                'contact_email' => $org['contact_email'],
                'contact_phone' => $org['contact_phone'],
                'address' => $org['address'],
                'state' => $org['state'],
                'postcode' => $org['postcode'],
                'stripe_customer_id' => null,
                'is_verified' => $org['is_verified'],
                'logo_url' => $org['logo_url'],
                'brand_primary_color' => $org['brand_primary_color'],
                'brand_secondary_color' => $org['brand_secondary_color'],
                'licensed_staff_seats' => $org['licensed_staff_seats'],
                'deleted_at' => null,
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('organizations')->where('id', $existing->id)->update($payload);
                $organizationId = $existing->id;
            } else {
                $organizationId = (string) Str::uuid();
                DB::table('organizations')->insert(array_merge($payload, [
                    'id' => $organizationId,
                    'created_at' => now(),
                ]));
            }

            $this->attachServices($organizationId, $type->id);
            $this->attachServiceGroups($organizationId, $type->id);
        }
    }

    private function attachServices(string $organizationId, string $typeId): void
    {
        $serviceIds = Service::where('type_id', $typeId)->pluck('id');
        $selected = $this->takeRandom($serviceIds, 3);

        foreach ($selected as $serviceId) {
            $exists = DB::table('organization_services')
                ->where('organization_id', $organizationId)
                ->where('service_id', $serviceId)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('organization_services')->insert([
                'id' => (string) Str::uuid(),
                'organization_id' => $organizationId,
                'service_id' => $serviceId,
                'description' => 'Package tailored to client needs.',
                'starting_price' => rand(150, 900),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function attachServiceGroups(string $organizationId, string $typeId): void
    {
        $groupIds = ServiceGroup::where('type_id', $typeId)->pluck('id');
        $selected = $this->takeRandom($groupIds, 1);

        foreach ($selected as $groupId) {
            $exists = DB::table('organization_service_groups')
                ->where('organization_id', $organizationId)
                ->where('service_group_id', $groupId)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('organization_service_groups')->insert([
                'id' => (string) Str::uuid(),
                'organization_id' => $organizationId,
                'service_group_id' => $groupId,
                'description' => 'Bundled offering for easier selection.',
                'package_price' => rand(900, 2500),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function takeRandom(Collection $items, int $count): Collection
    {
        if ($items->isEmpty()) {
            return collect();
        }

        return $items->shuffle()->take(min($count, $items->count()));
    }

    private function organizations(): array
    {
        return array_merge(
            $this->buildCategoryOrganizations(
                'real-estate',
                'organisation',
                11,
                ['Gold', 'Silver', 'Bronze', 'Platinum'],
                [
                    'Harborview Realty',
                    'Coastline Realty',
                    'Apex Property Group',
                    'Meridian Realty',
                    'Keystone Estates',
                    'Bluewater Realty',
                    'North Coast Property',
                    'Summit Realty Partners',
                    'Lighthouse Realty',
                    'Crownline Realty',
                ],
                ['Sydney', 'Gold Coast', 'Newcastle', 'Brisbane', 'Wollongong', 'Melbourne', 'Geelong', 'Adelaide', 'Perth', 'Hobart'],
                ['NSW', 'QLD', 'NSW', 'QLD', 'NSW', 'VIC', 'VIC', 'SA', 'WA', 'TAS'],
                ['2000', '4217', '2300', '4000', '2500', '3000', '3220', '5000', '6000', '7000'],
                ['#0E4D92', '#0A9396', '#1B998B', '#2A9D8F', '#457B9D', '#264653', '#3D5A80', '#5C6B73', '#6C757D', '#2F3E46'],
                ['#F2C14E', '#94D2BD', '#F4D35E', '#D9ED92', '#E9C46A', '#E76F51', '#A8DADC', '#CDB4DB', '#BDE0FE', '#FFD166'],
                ['verified', 'pending', 'verified', 'verified', 'rejected', 'verified', 'pending', 'verified', 'verified', 'pending']
            ),
            $this->buildCategoryOrganizations(
                'buyers-agent',
                'organisation',
                22,
                ['Buyer Network', 'Buyer Assist'],
                [
                    'Buyer Edge Partners',
                    'Harbour Buyers',
                    'Priority Buyer Group',
                    'Strategic Property Buyers',
                    'Focus Buyer Advisory',
                    'Urban Buyer Collective',
                    'ClearPath Buyers',
                    'Prime Buyer Solutions',
                    'Atlas Buyer Partners',
                    'Oak & Stone Buyers',
                ],
                ['Brisbane', 'Newcastle', 'Sydney', 'Melbourne', 'Perth', 'Adelaide', 'Canberra', 'Hobart', 'Gold Coast', 'Wollongong'],
                ['QLD', 'NSW', 'NSW', 'VIC', 'WA', 'SA', 'ACT', 'TAS', 'QLD', 'NSW'],
                ['4000', '2300', '2000', '3000', '6000', '5000', '2600', '7000', '4217', '2500'],
                ['#1B998B', '#5E548E', '#4D908E', '#577590', '#43AA8B', '#277DA1', '#6D597A', '#355070', '#84A59D', '#3D5A80'],
                ['#F4D35E', '#B8B8FF', '#E9C46A', '#F1FAEE', '#F6BD60', '#F7E1A0', '#D4A5A5', '#F28482', '#BDE0FE', '#CDB4DB'],
                ['verified', 'verified', 'pending', 'verified', 'verified', 'pending', 'verified', 'verified', 'pending', 'verified']
            ),
            $this->buildCategoryOrganizations(
                'builders',
                'company',
                33,
                ['Builder Enterprise', 'Builder Growth'],
                [
                    'Blueprint Builders',
                    'Northstar Builders',
                    'Metro Homes Builders',
                    'Summit Projects',
                    'BuildRight Constructions',
                    'Urban Build Co',
                    'Apex Construction Group',
                    'Foundation Build Partners',
                    'Cornerstone Builders',
                    'Horizon Build Group',
                ],
                ['Melbourne', 'Adelaide', 'Perth', 'Darwin', 'Brisbane', 'Sydney', 'Canberra', 'Geelong', 'Hobart', 'Newcastle'],
                ['VIC', 'SA', 'WA', 'NT', 'QLD', 'NSW', 'ACT', 'VIC', 'TAS', 'NSW'],
                ['3000', '5000', '6000', '0800', '4000', '2000', '2600', '3220', '7000', '2300'],
                ['#7A6C5D', '#5D5B6A', '#6C584C', '#8D6E63', '#4E6E58', '#526D82', '#3D5A80', '#6D6875', '#8B5E3C', '#2F4858'],
                ['#E6CCB2', '#C9ADA7', '#F1D3B3', '#E3A857', '#B7B7A4', '#D8D2CB', '#E0E1DD', '#CDE7BE', '#F4D6CC', '#B8E0D2'],
                ['verified', 'verified', 'verified', 'pending', 'verified', 'verified', 'pending', 'verified', 'verified', 'pending']
            ),
            $this->buildCategoryOrganizations(
                'trades-professionals',
                'company',
                44,
                ['Starter', 'Growth', 'Elite', 'Enterprise'],
                [
                    'BrightPath Home Services',
                    'Precision Trades Group',
                    'Northside Electrical Co',
                    'Harbour Plumbing & Co',
                    'Urban Trade Co',
                    'Apex Handyman Services',
                    'ProFix Property Services',
                    'TrueLine Maintenance',
                    'AllState Trades',
                    'Keyline Services',
                ],
                ['Perth', 'Melbourne', 'Newcastle', 'Hobart', 'Canberra', 'Sydney', 'Brisbane', 'Adelaide', 'Gold Coast', 'Wollongong'],
                ['WA', 'VIC', 'NSW', 'TAS', 'ACT', 'NSW', 'QLD', 'SA', 'QLD', 'NSW'],
                ['6000', '3000', '2300', '7000', '2600', '2000', '4000', '5000', '4217', '2500'],
                ['#F76C5E', '#005F73', '#0A9396', '#3A86FF', '#8338EC', '#FF006E', '#FB5607', '#2A9D8F', '#E63946', '#6A0572'],
                ['#6A0572', '#94D2BD', '#0A9396', '#8338EC', '#A8DADC', '#F4A261', '#FFE5B4', '#B8B8FF', '#F4D35E', '#CDB4DB'],
                ['verified', 'verified', 'verified', 'verified', 'pending', 'verified', 'verified', 'pending', 'verified', 'rejected']
            )
        );
    }

    private function buildCategoryOrganizations(
        string $typeSlug,
        string $businessType,
        int $categoryCode,
        array $planCycle,
        array $names,
        array $cities,
        array $states,
        array $postcodes,
        array $primaryColors,
        array $secondaryColors,
        array $verificationStatuses
    ): array {
        $organizations = [];

        foreach ($names as $index => $name) {
            $position = $index + 1;
            $slug = Str::slug($name);
            $city = $cities[$index] ?? $cities[array_key_last($cities)];
            $state = $states[$index] ?? $states[array_key_last($states)];
            $postcode = $postcodes[$index] ?? $postcodes[array_key_last($postcodes)];
            $primaryColor = $primaryColors[$index] ?? $primaryColors[array_key_last($primaryColors)];
            $secondaryColor = $secondaryColors[$index] ?? $secondaryColors[array_key_last($secondaryColors)];
            $verificationStatus = $verificationStatuses[$index] ?? 'verified';

            $organizations[] = [
                'name' => $name,
                'slug' => $slug,
                'type_slug' => $typeSlug,
                'business_type' => $businessType,
                'business_verification_status' => $verificationStatus,
                'abn' => sprintf('%011d', $categoryCode * 100000000 + $position),
                'acn' => sprintf('%09d', $categoryCode * 1000000 + $position),
                'contact_email' => sprintf('hello@%s.example', $slug),
                'contact_phone' => sprintf('+61 %d 8%03d %04d', ($categoryCode % 9) + 1, $categoryCode, 1000 + $position),
                'address' => sprintf('%d %s Avenue, %s %s', 10 + $position, ucwords(str_replace('-', ' ', $typeSlug)), $city, $state),
                'state' => $state,
                'postcode' => $postcode,
                'logo_url' => sprintf('https://example.com/logos/%s.png', $slug),
                'brand_primary_color' => $primaryColor,
                'brand_secondary_color' => $secondaryColor,
                'licensed_staff_seats' => $this->licensedSeatsFor($businessType, $position),
                'ranking_priority' => $position,
                'is_verified' => $verificationStatus === 'verified',
            ];
        }

        return $organizations;
    }

    private function licensedSeatsFor(string $businessType, int $position): int
    {
        return $businessType === 'organisation'
            ? 4 + $position
            : 2 + ($position * 2);
    }
}
