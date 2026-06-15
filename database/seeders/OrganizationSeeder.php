<?php

namespace Database\Seeders;

use App\Models\OrganizationType;
use App\Models\Service;
use App\Models\ServiceGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $types = OrganizationType::all()->keyBy('slug');

        $organizations = [
            [
                'name' => 'Harborview Realty',
                'slug' => 'harborview-realty',
                'type_slug' => 'real-estate',
                'abn' => '11111111111',
                'acn' => '111111111',
                'contact_email' => 'hello@harborview.example',
                'contact_phone' => '+61 2 8000 1111',
                'logo_url' => 'https://example.com/logos/harborview.png',
                'brand_primary_color' => '#0E4D92',
                'brand_secondary_color' => '#F2C14E',
                'licensed_staff_seats' => 12,
                'ranking_priority' => 3,
                'is_verified' => true,
            ],
            [
                'name' => 'Sunrise Property Group',
                'slug' => 'sunrise-property-group',
                'type_slug' => 'property-management',
                'abn' => '22222222222',
                'acn' => '222222222',
                'contact_email' => 'support@sunrise.example',
                'contact_phone' => '+61 7 4000 2222',
                'logo_url' => 'https://example.com/logos/sunrise.png',
                'brand_primary_color' => '#1B998B',
                'brand_secondary_color' => '#F4D35E',
                'licensed_staff_seats' => 8,
                'ranking_priority' => 2,
                'is_verified' => true,
            ],
            [
                'name' => 'Willowbrook Aged Care',
                'slug' => 'willowbrook-aged-care',
                'type_slug' => 'aged-care',
                'abn' => '33333333333',
                'acn' => '333333333',
                'contact_email' => 'contact@willowbrook.example',
                'contact_phone' => '+61 3 9000 3333',
                'logo_url' => 'https://example.com/logos/willowbrook.png',
                'brand_primary_color' => '#7A6C5D',
                'brand_secondary_color' => '#E6CCB2',
                'licensed_staff_seats' => 25,
                'ranking_priority' => 4,
                'is_verified' => true,
            ],
            [
                'name' => 'BrightPath Home Services',
                'slug' => 'brightpath-home-services',
                'type_slug' => 'home-services',
                'abn' => '44444444444',
                'acn' => '444444444',
                'contact_email' => 'hi@brightpath.example',
                'contact_phone' => '+61 8 5000 4444',
                'logo_url' => 'https://example.com/logos/brightpath.png',
                'brand_primary_color' => '#F76C5E',
                'brand_secondary_color' => '#6A0572',
                'licensed_staff_seats' => 6,
                'ranking_priority' => 1,
                'is_verified' => false,
            ],
            [
                'name' => 'Northside Electrical Co',
                'slug' => 'northside-electrical-co',
                'type_slug' => 'solo-traders',
                'abn' => '55555555555',
                'acn' => '555555555',
                'contact_email' => 'hello@northsideelectrical.example',
                'contact_phone' => '+61 2 5000 5555',
                'logo_url' => 'https://example.com/logos/northside-electrical.png',
                'brand_primary_color' => '#005F73',
                'brand_secondary_color' => '#0A9396',
                'licensed_staff_seats' => 3,
                'ranking_priority' => 2,
                'is_verified' => true,
            ],
            [
                'name' => 'Harbour Plumbing & Co',
                'slug' => 'harbour-plumbing-co',
                'type_slug' => 'solo-traders',
                'abn' => '66666666666',
                'acn' => '666666666',
                'contact_email' => 'support@harbourplumbing.example',
                'contact_phone' => '+61 3 5000 6666',
                'logo_url' => 'https://example.com/logos/harbour-plumbing.png',
                'brand_primary_color' => '#3A86FF',
                'brand_secondary_color' => '#8338EC',
                'licensed_staff_seats' => 4,
                'ranking_priority' => 1,
                'is_verified' => true,
            ],
        ];

        foreach ($organizations as $org) {
            $type = $types->get($org['type_slug']);
            if (!$type) {
                continue;
            }

            $existing = DB::table('organizations')->where('slug', $org['slug'])->first();
            $payload = [
                'plan_id' => null,
                'type_id' => $type->id,
                'ranking_priority' => $org['ranking_priority'],
                'avg_org_rating' => $org['ranking_priority'] >= 3 ? 4.4 : 4.0,
                'name' => $org['name'],
                'slug' => $org['slug'],
                'abn' => $org['abn'],
                'acn' => $org['acn'],
                'contact_email' => $org['contact_email'],
                'contact_phone' => $org['contact_phone'],
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
}
