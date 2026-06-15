<?php

namespace Database\Seeders;

use App\Models\OrganizationType;
use App\Models\Service;
use App\Models\ServiceGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServiceGroupSeeder extends Seeder
{
    public function run(): void
    {
        $types = OrganizationType::all()->keyBy('slug');
        $services = Service::all()->keyBy('slug');

        $groupsByType = [
            'real-estate' => [
                [
                    'name' => 'Sales and Marketing',
                    'slug' => 'sales-marketing',
                    'description' => 'Listing, appraisal, and marketing services.',
                    'services' => ['sales-appraisal', 'property-listing', 'open-home-hosting', 'property-styling'],
                ],
            ],
            'property-management' => [
                [
                    'name' => 'Core Management',
                    'slug' => 'core-management',
                    'description' => 'End-to-end property management services.',
                    'services' => ['tenant-screening', 'rent-collection', 'maintenance-coordination', 'routine-inspections'],
                ],
            ],
            'aged-care' => [
                [
                    'name' => 'Care Packages',
                    'slug' => 'care-packages',
                    'description' => 'Tailored support for different care needs.',
                    'services' => ['independent-living', 'assisted-living', 'memory-care'],
                ],
            ],
            'home-services' => [
                [
                    'name' => 'Home Upkeep',
                    'slug' => 'home-upkeep',
                    'description' => 'Cleaning, maintenance, and outdoor care.',
                    'services' => ['cleaning', 'landscaping', 'general-maintenance'],
                ],
            ],
            'solo-traders' => [
                [
                    'name' => 'Solo Trader Services',
                    'slug' => 'solo-trader-services',
                    'description' => 'Core services offered by solo trader businesses.',
                    'services' => ['electrical', 'plumbing', 'fencing', 'landscapers', 'conveyancers', 'brokers'],
                ],
            ],
        ];

        foreach ($groupsByType as $typeSlug => $groups) {
            $type = $types->get($typeSlug);
            if (!$type) {
                continue;
            }

            foreach ($groups as $group) {
                $serviceGroup = ServiceGroup::withTrashed()->updateOrCreate(
                    ['slug' => $group['slug']],
                    [
                        'type_id' => $type->id,
                        'name' => $group['name'],
                        'description' => $group['description'],
                        'deleted_at' => null,
                    ]
                );

                foreach ($group['services'] as $serviceSlug) {
                    $service = $services->get($serviceSlug);
                    if (!$service) {
                        continue;
                    }

                    $exists = DB::table('service_group_services')
                        ->where('service_group_id', $serviceGroup->id)
                        ->where('service_id', $service->id)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('service_group_services')->insert([
                        'id' => (string) Str::uuid(),
                        'service_group_id' => $serviceGroup->id,
                        'service_id' => $service->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
