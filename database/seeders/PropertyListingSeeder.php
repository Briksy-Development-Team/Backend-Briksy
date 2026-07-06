<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use App\Services\DynamicIdGeneratorService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PropertyListingSeeder extends Seeder
{
    public function run(): void
    {
        $generator = app(DynamicIdGeneratorService::class);
        $propertyTypeIds = DB::table('property_types')->pluck('id');

        $templates = [
            [
                'title' => 'Coastal Retreat with Ocean Views',
                'description' => 'Light-filled home with generous outdoor living and modern finishes.',
                'status' => 'Published',
                'suburb' => 'Bondi',
                'postcode' => '2026',
                'latitude' => -33.890842,
                'longitude' => 151.274292,
                'property_condition' => 'established',
                'bedroom_option' => '3',
                'bathroom_option' => '2',
                'car_space_option' => '1',
                'land_area_sqm' => 420.50,
                'floor_area_sqm' => 180.75,
                'frontage_width_m' => 14.20,
            ],
            [
                'title' => 'Modern Townhouse Near Transport',
                'description' => 'Low-maintenance living with open-plan design and secure parking.',
                'status' => 'Published',
                'suburb' => 'Richmond',
                'postcode' => '3121',
                'latitude' => -37.818271,
                'longitude' => 145.001622,
                'property_condition' => 'new',
                'bedroom_option' => '2',
                'bathroom_option' => '2',
                'car_space_option' => '2',
                'land_area_sqm' => 210.00,
                'floor_area_sqm' => 145.40,
                'frontage_width_m' => 9.60,
            ],
            [
                'title' => 'Single Level Villa with Garden',
                'description' => 'Quiet villa ideal for downsizers, featuring accessible design.',
                'status' => 'Draft',
                'suburb' => 'Glenelg',
                'postcode' => '5045',
                'latitude' => -34.980492,
                'longitude' => 138.516980,
                'property_condition' => 'established',
                'bedroom_option' => '2',
                'bathroom_option' => '1',
                'car_space_option' => '1',
                'land_area_sqm' => 320.00,
                'floor_area_sqm' => 120.00,
                'frontage_width_m' => 12.50,
            ],
        ];

        $organizations = Organization::all();

        foreach ($organizations as $org) {
            $creator = User::where('organization_id', $org->id)->first();
            if (!$creator) {
                continue;
            }

            foreach ($templates as $index => $template) {
                $title = $template['title'] . ' - ' . ($index + 1);
                $propertyTypeId = $propertyTypeIds->isEmpty()
                    ? null
                    : $propertyTypeIds->shuffle()->first();

                $existing = DB::table('property_listings')
                    ->where('org_id', $org->id)
                    ->where('title', $title)
                    ->first();

                $payload = [
                    'org_id' => $org->id,
                    'creator_id' => $creator->id,
                    'generated_id' => $existing?->generated_id ?? $generator->generate('properties', 'PROP') ?? ('PROP-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6))),
                    'avg_prop_rating' => 4.2,
                    'latitude' => $template['latitude'],
                    'longitude' => $template['longitude'],
                    'title' => $title,
                    'description' => $template['description'],
                    'status' => $template['status'],
                    'suburb' => $template['suburb'],
                    'postcode' => $template['postcode'],
                    'embedding' => null,
                    'property_type_id' => $propertyTypeId,
                    'property_condition' => $template['property_condition'],
                    'land_area_sqm' => $template['land_area_sqm'],
                    'floor_area_sqm' => $template['floor_area_sqm'],
                    'frontage_width_m' => $template['frontage_width_m'],
                    'bedroom_option' => $template['bedroom_option'],
                    'bathroom_option' => $template['bathroom_option'],
                    'car_space_option' => $template['car_space_option'],
                    'deleted_at' => null,
                    'updated_at' => now(),
                ];

                if ($existing) {
                    DB::table('property_listings')->where('id', $existing->id)->update($payload);
                } else {
                    DB::table('property_listings')->insert(array_merge($payload, [
                        'id' => (string) Str::uuid(),
                        'created_at' => now(),
                    ]));
                }
            }
        }
    }
}
