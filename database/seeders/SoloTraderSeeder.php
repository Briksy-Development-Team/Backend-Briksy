<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\OrganizationType;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Services\DynamicIdGeneratorService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SoloTraderSeeder extends Seeder
{
    public function run(): void
    {
        $generator = app(DynamicIdGeneratorService::class);
        $type = OrganizationType::withTrashed()->updateOrCreate(
            ['slug' => 'solo-traders'],
            [
                'name' => 'Solo Traders',
                'deleted_at' => null,
            ]
        );

        $services = [
            ['name' => 'Electrical', 'slug' => 'electrical', 'description' => 'Electrical installation, repair, and maintenance services.'],
            ['name' => 'Plumbing', 'slug' => 'plumbing', 'description' => 'Plumbing repair, installation, and maintenance services.'],
            ['name' => 'Fencing', 'slug' => 'fencing', 'description' => 'Fence installation and repair services.'],
            ['name' => 'Landscapers', 'slug' => 'landscapers', 'description' => 'Outdoor maintenance and landscaping services.'],
            ['name' => 'Conveyancers', 'slug' => 'conveyancers', 'description' => 'Property transfer and conveyancing services.'],
            ['name' => 'Brokers', 'slug' => 'brokers', 'description' => 'Brokerage and deal facilitation services.'],
        ];

        foreach ($services as $serviceData) {
            Service::withTrashed()->updateOrCreate(
                ['slug' => $serviceData['slug']],
                [
                    'type_id' => $type->id,
                    'name' => $serviceData['name'],
                    'description' => $serviceData['description'],
                    'is_active' => true,
                    'deleted_at' => null,
                ]
            );
        }

        $serviceGroup = ServiceGroup::withTrashed()->updateOrCreate(
            ['slug' => 'solo-trader-services'],
            [
                'type_id' => $type->id,
                'name' => 'Solo Trader Services',
                'description' => 'Core services offered by solo trader businesses.',
                'deleted_at' => null,
            ]
        );

        $serviceIds = Service::query()
            ->where('type_id', $type->id)
            ->whereIn('slug', array_column($services, 'slug'))
            ->pluck('id');

        foreach ($serviceIds as $serviceId) {
            $exists = DB::table('service_group_services')
                ->where('service_group_id', $serviceGroup->id)
                ->where('service_id', $serviceId)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('service_group_services')->insert([
                'id' => (string) Str::uuid(),
                'service_group_id' => $serviceGroup->id,
                'service_id' => $serviceId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $organizations = [
            [
                'name' => 'Northside Electrical Co',
                'slug' => 'northside-electrical-co',
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

        foreach ($organizations as $data) {
            $existing = DB::table('organizations')->where('slug', $data['slug'])->first();

            $payload = [
                'plan_id' => null,
                'type_id' => $type->id,
                'generated_id' => $existing?->generated_id ?? $generator->generate('organizations'),
                'ranking_priority' => $data['ranking_priority'],
                'avg_org_rating' => 4.2,
                'name' => $data['name'],
                'slug' => $data['slug'],
                'abn' => $data['abn'],
                'acn' => $data['acn'],
                'contact_email' => $data['contact_email'],
                'contact_phone' => $data['contact_phone'],
                'stripe_customer_id' => null,
                'is_verified' => $data['is_verified'],
                'logo_url' => $data['logo_url'],
                'brand_primary_color' => $data['brand_primary_color'],
                'brand_secondary_color' => $data['brand_secondary_color'],
                'licensed_staff_seats' => $data['licensed_staff_seats'],
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

            $this->syncServiceLinks($organizationId, $serviceIds->all());
        }
    }

    private function syncServiceLinks(string $organizationId, array $serviceIds): void
    {
        foreach ($serviceIds as $serviceId) {
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
                'description' => null,
                'starting_price' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $groupExists = DB::table('organization_service_groups')
            ->where('organization_id', $organizationId)
            ->where('service_group_id', DB::table('service_groups')->where('slug', 'solo-trader-services')->value('id'))
            ->exists();

        if ($groupExists) {
            return;
        }

        $groupId = DB::table('service_groups')->where('slug', 'solo-trader-services')->value('id');
        if (!$groupId) {
            return;
        }

        DB::table('organization_service_groups')->insert([
            'id' => (string) Str::uuid(),
            'organization_id' => $organizationId,
            'service_group_id' => $groupId,
            'description' => 'Bundled offering for solo trader businesses.',
            'package_price' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
