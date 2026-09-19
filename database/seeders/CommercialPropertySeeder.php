<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use App\Services\DynamicIdGeneratorService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommercialPropertySeeder extends Seeder
{
    public function run(): void
    {
        $generator = app(DynamicIdGeneratorService::class);
        $propertyTypes = DB::table('property_types')->pluck('id', 'slug');

        $templates = [
            [
                'title' => 'High-Exposure CBD Office Investment',
                'description' => 'Fully fitted office floor with reception, meeting rooms, secure parking, and strong CBD tenant demand.',
                'property_type_slug' => 'office',
                'suburb' => 'Melbourne',
                'state' => 'VIC',
                'postcode' => '3000',
                'latitude' => -37.813629,
                'longitude' => 144.963058,
                'price' => 2850000,
                'listing_purpose' => 'SELL',
                'land_area_sqm' => 410,
                'floor_area_sqm' => 410,
                'frontage_width_m' => 18,
            ],
            [
                'title' => 'Neighbourhood Retail Centre Opportunity',
                'description' => 'Convenience-led retail centre with established tenants, excellent visibility, and ample customer parking.',
                'property_type_slug' => 'retail',
                'suburb' => 'Parramatta',
                'state' => 'NSW',
                'postcode' => '2150',
                'latitude' => -33.815102,
                'longitude' => 151.001137,
                'price' => 4600000,
                'listing_purpose' => 'SELL',
                'land_area_sqm' => 1850,
                'floor_area_sqm' => 980,
                'frontage_width_m' => 42,
            ],
            [
                'title' => 'Modern Industrial Warehouse for Lease',
                'description' => 'Clear-span warehouse with container access, loading dock, hardstand, and modern office accommodation.',
                'property_type_slug' => 'warehouse',
                'suburb' => 'Dandenong South',
                'state' => 'VIC',
                'postcode' => '3175',
                'latitude' => -38.016670,
                'longitude' => 145.216667,
                'price' => 185000,
                'listing_purpose' => 'RENT',
                'land_area_sqm' => 3200,
                'floor_area_sqm' => 2100,
                'frontage_width_m' => 36,
            ],
            [
                'title' => 'Boutique Showroom and Trade Outlet',
                'description' => 'Flexible showroom with high ceilings, showroom frontage, rear storage, and convenient arterial-road access.',
                'property_type_slug' => 'showroom',
                'suburb' => 'Geebung',
                'state' => 'QLD',
                'postcode' => '4034',
                'latitude' => -27.372500,
                'longitude' => 153.049700,
                'price' => 1250000,
                'listing_purpose' => 'SELL',
                'land_area_sqm' => 1120,
                'floor_area_sqm' => 760,
                'frontage_width_m' => 25,
            ],
            [
                'title' => 'Flexible Commercial Development Site',
                'description' => 'Rare development parcel in a growing commercial precinct with flexible positioning for office, retail, or service uses.',
                'property_type_slug' => 'commercial',
                'suburb' => 'Mawson Lakes',
                'state' => 'SA',
                'postcode' => '5095',
                'latitude' => -34.811000,
                'longitude' => 138.613000,
                'price' => 980000,
                'listing_purpose' => 'BOTH',
                'land_area_sqm' => 2400,
                'floor_area_sqm' => 0,
                'frontage_width_m' => 48,
            ],
            [
                'title' => 'Established Light Industrial Facility',
                'description' => 'Secure industrial facility with workshop space, offices, staff amenities, and a large fenced yard.',
                'property_type_slug' => 'industrial',
                'suburb' => 'Osborne Park',
                'state' => 'WA',
                'postcode' => '6017',
                'latitude' => -31.900000,
                'longitude' => 115.810000,
                'price' => 2150000,
                'listing_purpose' => 'SELL',
                'land_area_sqm' => 2750,
                'floor_area_sqm' => 1450,
                'frontage_width_m' => 31,
            ],
        ];

        $organizations = Organization::query()
            ->whereHas('organizationType', fn ($query) => $query->where('slug', 'real-estate'))
            ->get();

        foreach ($organizations as $organization) {
            $creator = User::query()->where('organization_id', $organization->id)->first();

            if (!$creator) {
                continue;
            }

            foreach ($templates as $template) {
                $propertyTypeId = $propertyTypes->get($template['property_type_slug']);

                if (!$propertyTypeId) {
                    continue;
                }

                $title = $template['title'] . ' - ' . $organization->slug;
                $existing = DB::table('property_listings')
                    ->where('org_id', $organization->id)
                    ->where('title', $title)
                    ->first();

                $payload = [
                    'org_id' => $organization->id,
                    'creator_id' => $creator->id,
                    'generated_id' => $existing?->generated_id ?? $generator->generate('properties'),
                    'property_type_id' => $propertyTypeId,
                    'avg_prop_rating' => 4.3,
                    'latitude' => $template['latitude'],
                    'longitude' => $template['longitude'],
                    'title' => $title,
                    'description' => $template['description'],
                    'status' => 'Published',
                    'listing_purpose' => $template['listing_purpose'],
                    'price' => $template['price'],
                    'suburb' => $template['suburb'],
                    'state' => $template['state'],
                    'postcode' => $template['postcode'],
                    'country' => 'Australia',
                    'location_verified' => true,
                    'submitted_at' => now(),
                    'reviewed_at' => now(),
                    'published_at' => now(),
                    'land_area_sqm' => $template['land_area_sqm'],
                    'floor_area_sqm' => $template['floor_area_sqm'],
                    'frontage_width_m' => $template['frontage_width_m'],
                    'property_condition' => 'established',
                    'embedding' => null,
                    'deleted_at' => null,
                    'updated_at' => now(),
                ];

                if ($existing) {
                    DB::table('property_listings')->where('id', $existing->id)->update($payload);
                    continue;
                }

                DB::table('property_listings')->insert(array_merge($payload, [
                    'id' => (string) Str::uuid(),
                    'created_at' => now(),
                ]));
            }
        }
    }
}
