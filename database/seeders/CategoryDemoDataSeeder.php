<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Service;
use App\Models\OrganizationType;
use App\Models\BuyerBrief;
use App\Models\BuilderProject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoryDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedBuyerBriefs();
        $this->seedBuilderProjects();
        $tradeType = OrganizationType::query()->where('slug', 'trades-professionals')->first();
        if (!$tradeType) {
            return;
        }

        $serviceCatalog = [
            ['name' => 'Electrical Maintenance', 'category' => 'Electrical', 'rate_from' => 120, 'rate_to' => 260],
            ['name' => 'Emergency Plumbing', 'category' => 'Plumbing', 'rate_from' => 140, 'rate_to' => 320],
            ['name' => 'Landscape Design and Care', 'category' => 'Landscaping', 'rate_from' => 95, 'rate_to' => 220],
            ['name' => 'Custom Carpentry', 'category' => 'Carpentry', 'rate_from' => 110, 'rate_to' => 280],
        ];

        $areas = [
            ['name' => 'Melbourne Inner North', 'state' => 'VIC', 'suburb' => 'Brunswick', 'postcode' => '3056', 'lat' => -37.7667, 'lng' => 144.9614],
            ['name' => 'Sydney Eastern Suburbs', 'state' => 'NSW', 'suburb' => 'Randwick', 'postcode' => '2031', 'lat' => -33.9138, 'lng' => 151.2410],
            ['name' => 'Brisbane Southside', 'state' => 'QLD', 'suburb' => 'Eight Mile Plains', 'postcode' => '4113', 'lat' => -27.5833, 'lng' => 153.1000],
            ['name' => 'Perth Coastal Corridor', 'state' => 'WA', 'suburb' => 'Scarborough', 'postcode' => '6019', 'lat' => -31.8958, 'lng' => 115.7560],
        ];

        $organizations = Organization::query()
            ->whereHas('organizationType', fn ($query) => $query->where('slug', 'trades-professionals'))
            ->orderBy('slug')
            ->get();

        foreach ($organizations as $index => $organization) {
            foreach ([0, 1] as $serviceOffset) {
                $area = $areas[($index + $serviceOffset) % count($areas)];
                $service = $serviceCatalog[($index + $serviceOffset) % count($serviceCatalog)];
                $slug = $organization->slug . '-' . Str::slug($service['category']) . '-' . ($serviceOffset + 1);
                $geometry = $this->coveragePolygon($area['lat'], $area['lng']);

                $ownedService = Service::withTrashed()->updateOrCreate(
                ['slug' => $slug],
                [
                    'type_id' => $tradeType->id,
                    'organization_id' => $organization->id,
                    'name' => $service['name'],
                    'title' => $service['name'],
                    'category' => $service['category'],
                    'description' => sprintf('%s delivered by %s across %s and nearby suburbs.', $service['name'], $organization->name, $area['name']),
                    'service_area' => sprintf('%s, %s %s', $area['suburb'], $area['state'], $area['postcode']),
                    'service_area_geometry' => $geometry,
                    'rate_from' => $service['rate_from'],
                    'rate_to' => $service['rate_to'],
                    'is_active' => true,
                    'deleted_at' => null,
                ]
                );

                DB::table('organization_services')->updateOrInsert(
                ['organization_id' => $organization->id, 'service_id' => $ownedService->id],
                [
                    'id' => DB::table('organization_services')
                        ->where('organization_id', $organization->id)
                        ->where('service_id', $ownedService->id)
                        ->value('id') ?? (string) Str::uuid(),
                    'description' => $ownedService->description,
                    'starting_price' => $service['rate_from'],
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
                );
            }
        }
    }

    private function seedBuyerBriefs(): void
    {
        $organizations = Organization::whereHas('organizationType', fn ($query) => $query->where('slug', 'buyers-agent'))->get();
        foreach ($organizations as $organization) {
            $user = User::where('organization_id', $organization->id)->first();
            foreach ([1, 2] as $number) {
                BuyerBrief::updateOrCreate(
                    ['organization_id' => $organization->id, 'client_email' => "buyer{$number}@{$organization->slug}.demo"],
                    ['created_by' => $user?->id, 'client_name' => $number === 1 ? 'Mia and Daniel Harper' : 'Ethan Wallace', 'status' => $number === 1 ? 'active' : 'shortlisted', 'budget_min' => 750000, 'budget_max' => 1250000, 'preferred_locations' => ['Melbourne', 'Brisbane'], 'preferences' => ['bedrooms' => 3, 'property_type' => 'house'], 'notes' => 'Seeded buyer brief for the Buyers Agent workflow.']
                );
            }
        }
    }

    private function seedBuilderProjects(): void
    {
        $organizations = Organization::whereHas('organizationType', fn ($query) => $query->where('slug', 'builders'))->get();
        foreach ($organizations as $index => $organization) {
            $user = User::where('organization_id', $organization->id)->first();
            foreach ([1, 2] as $number) {
                BuilderProject::updateOrCreate(
                    ['organization_id' => $organization->id, 'name' => "{$organization->name} Project {$number}"],
                    ['created_by' => $user?->id, 'project_type' => $number === 1 ? 'Townhouse Development' : 'Custom Family Homes', 'status' => $number === 1 ? 'in_delivery' : 'planning', 'description' => 'Seeded builder project demonstrating project listings, client updates, and site planning.', 'location' => $index % 2 === 0 ? 'Richmond' : 'Newcastle', 'state' => $index % 2 === 0 ? 'VIC' : 'NSW', 'postcode' => $index % 2 === 0 ? '3121' : '2300', 'latitude' => $index % 2 === 0 ? -37.8183 : -32.9283, 'longitude' => $index % 2 === 0 ? 145.0016 : 151.7817]
                );
            }
        }
    }

    private function coveragePolygon(float $latitude, float $longitude): array
    {
        $lat = 0.075;
        $lng = 0.09;

        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [$longitude - $lng, $latitude - $lat],
                [$longitude + $lng, $latitude - $lat],
                [$longitude + $lng, $latitude + $lat],
                [$longitude - $lng, $latitude + $lat],
                [$longitude - $lng, $latitude - $lat],
            ]],
        ];
    }
}
