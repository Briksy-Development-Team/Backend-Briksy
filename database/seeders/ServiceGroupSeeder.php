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
                    'description' => 'Listing, appraisal, and buyer-facing marketing services.',
                    'services' => ['sales-appraisal', 'property-listing', 'open-home-hosting', 'property-styling', 'buyer-enquiry-management', 'auction-campaigns', 'vendor-reporting', 'property-photography-coordination', 'listing-copywriting', 'real-estate-crm-setup'],
                ],
            ],
            'buyers-agent' => [
                [
                    'name' => 'Buyer Services',
                    'slug' => 'buyer-services',
                    'description' => 'Briefing, shortlist, and negotiation services for buyers agents.',
                    'services' => ['buyer-briefs', 'property-shortlists', 'search-management', 'client-updates', 'negotiation-support', 'off-market-access', 'suburb-research', 'due-diligence-coordination', 'settlement-support', 'investor-strategy'],
                ],
            ],
            'builders' => [
                [
                    'name' => 'Build Operations',
                    'slug' => 'build-operations',
                    'description' => 'Planning, tendering, and site coordination for builders.',
                    'services' => ['project-planning', 'tender-management', 'site-notes', 'builder-client-updates', 'project-listings', 'variation-management', 'schedule-tracking', 'progress-claims', 'defect-management', 'handover-packs'],
                ],
            ],
            'trades-professionals' => [
                [
                    'name' => 'Trade Essentials',
                    'slug' => 'solo-trader-services',
                    'description' => 'Core services offered by trade and professional businesses.',
                    'services' => [
                        'electrical',
                        'plumbing',
                        'fencing',
                        'landscapers',
                        'excavation',
                        'concreting',
                        'carpentry',
                        'painting',
                        'roofing',
                        'air-conditioning',
                        'handyman',
                        'tiling',
                        'pest-control-solo',
                        'conveyancers',
                        'brokers',
                        'solar-installation',
                        'waterproofing',
                        'fire-safety',
                    ],
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
