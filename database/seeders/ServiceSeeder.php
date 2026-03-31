<?php

namespace Database\Seeders;

use App\Models\OrganizationType;
use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $types = OrganizationType::all()->keyBy('slug');

        $servicesByType = [
            'real-estate' => [
                ['name' => 'Sales Appraisal', 'slug' => 'sales-appraisal', 'description' => 'Market appraisal and pricing guidance.'],
                ['name' => 'Property Listing', 'slug' => 'property-listing', 'description' => 'End-to-end listing setup and marketing.'],
                ['name' => 'Open Home Hosting', 'slug' => 'open-home-hosting', 'description' => 'Open home preparation and hosting.'],
                ['name' => 'Property Styling', 'slug' => 'property-styling', 'description' => 'Staging advice to maximize appeal.'],
            ],
            'property-management' => [
                ['name' => 'Tenant Screening', 'slug' => 'tenant-screening', 'description' => 'Background checks and reference verification.'],
                ['name' => 'Rent Collection', 'slug' => 'rent-collection', 'description' => 'Automated rent collection and arrears follow-up.'],
                ['name' => 'Maintenance Coordination', 'slug' => 'maintenance-coordination', 'description' => 'Repairs and contractor management.'],
                ['name' => 'Routine Inspections', 'slug' => 'routine-inspections', 'description' => 'Scheduled inspections and reporting.'],
            ],
            'aged-care' => [
                ['name' => 'Independent Living', 'slug' => 'independent-living', 'description' => 'Independent living support services.'],
                ['name' => 'Assisted Living', 'slug' => 'assisted-living', 'description' => 'Daily living support and assistance.'],
                ['name' => 'Memory Care', 'slug' => 'memory-care', 'description' => 'Specialized dementia care programs.'],
            ],
            'home-services' => [
                ['name' => 'Cleaning', 'slug' => 'cleaning', 'description' => 'Regular and deep cleaning services.'],
                ['name' => 'Landscaping', 'slug' => 'landscaping', 'description' => 'Garden care and outdoor maintenance.'],
                ['name' => 'General Maintenance', 'slug' => 'general-maintenance', 'description' => 'Handyman and minor repairs.'],
            ],
        ];

        foreach ($servicesByType as $typeSlug => $services) {
            $type = $types->get($typeSlug);
            if (!$type) {
                continue;
            }

            foreach ($services as $service) {
                Service::withTrashed()->updateOrCreate(
                    ['slug' => $service['slug']],
                    [
                        'type_id' => $type->id,
                        'name' => $service['name'],
                        'description' => $service['description'],
                        'is_active' => true,
                        'deleted_at' => null,
                    ]
                );
            }
        }
    }
}
