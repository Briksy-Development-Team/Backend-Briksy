<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Media;
use App\Models\User;
use App\Services\DynamicIdGeneratorService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PropertyListingSeeder extends Seeder
{
    private const PROPERTY_STATUSES = [
        'Draft',
        'Pending Review',
        'Approved',
        'Rejected',
        'Published',
        'Archived',
    ];

    private const PROPERTY_CONDITIONS = ['new', 'established'];

    private const BEDROOM_OPTIONS = ['studio', '1', '2', '3', '4', '5_plus'];

    private const BATHROOM_OPTIONS = ['1', '2', '3_plus'];

    private const CAR_SPACE_OPTIONS = ['1', '2', '3_plus'];

    public function run(): void
    {
        $generator = app(DynamicIdGeneratorService::class);
        $propertyTypeIds = DB::table('property_types')->pluck('id', 'slug');

        $templates = [
            [
                'title' => 'Coastal Family Home with Ocean Views',
                'description' => 'Light-filled house with coastal aspect, generous outdoor living, and modern finishes.',
                'status' => 'Published',
                'suburb' => 'Bondi',
                'postcode' => '2026',
                'latitude' => -33.890842,
                'longitude' => 151.274292,
                'property_type_slug' => 'house',
                'property_condition' => 'established',
                'bedroom_option' => '3',
                'bathroom_option' => '2',
                'car_space_option' => '1',
                'land_area_sqm' => 420.50,
                'floor_area_sqm' => 180.75,
                'frontage_width_m' => 14.20,
            ],
            [
                'title' => 'Modern Apartment Near the CBD',
                'description' => 'Low-maintenance apartment with secure access, city convenience, and investment appeal.',
                'status' => 'Published',
                'suburb' => 'Southbank',
                'postcode' => '3006',
                'latitude' => -37.822856,
                'longitude' => 144.964945,
                'property_type_slug' => 'apartment-unit',
                'property_condition' => 'new',
                'bedroom_option' => '2',
                'bathroom_option' => '2',
                'car_space_option' => '1',
                'land_area_sqm' => 0.00,
                'floor_area_sqm' => 92.30,
                'frontage_width_m' => 0.00,
            ],
            [
                'title' => 'Townhouse for First Home Buyers',
                'description' => 'Well-planned townhouse with open living, secure parking, and easy transport access.',
                'status' => 'Pending Review',
                'suburb' => 'Richmond',
                'postcode' => '3121',
                'latitude' => -37.818271,
                'longitude' => 145.001622,
                'property_type_slug' => 'townhouse',
                'property_condition' => 'new',
                'bedroom_option' => '3',
                'bathroom_option' => '2',
                'car_space_option' => '2',
                'land_area_sqm' => 180.00,
                'floor_area_sqm' => 138.40,
                'frontage_width_m' => 8.90,
            ],
            [
                'title' => 'Vacant Land Ready for Development',
                'description' => 'Flat vacant site suitable for new build or subdivision planning.',
                'status' => 'Published',
                'suburb' => 'Coomera',
                'postcode' => '4209',
                'latitude' => -27.854610,
                'longitude' => 153.333240,
                'property_type_slug' => 'land',
                'property_condition' => 'new',
                'bedroom_option' => null,
                'bathroom_option' => null,
                'car_space_option' => null,
                'land_area_sqm' => 650.00,
                'floor_area_sqm' => 0.00,
                'frontage_width_m' => 18.00,
            ],
            [
                'title' => 'Rural Acreage Retreat with Shedding',
                'description' => 'Lifestyle acreage with room for equipment, hobby farming, and future improvements.',
                'status' => 'Draft',
                'suburb' => 'Glenelg',
                'postcode' => '5045',
                'latitude' => -34.980492,
                'longitude' => 138.516980,
                'property_type_slug' => 'acreage',
                'property_condition' => 'established',
                'bedroom_option' => '4',
                'bathroom_option' => '2',
                'car_space_option' => '3_plus',
                'land_area_sqm' => 4520.00,
                'floor_area_sqm' => 220.00,
                'frontage_width_m' => 35.00,
            ],
            [
                'title' => 'Retirement Living Villa with Courtyard',
                'description' => 'Single-level villa designed for downsizers with accessible access and private outdoor space.',
                'status' => 'Archived',
                'suburb' => 'Mooloolaba',
                'postcode' => '4557',
                'latitude' => -26.676920,
                'longitude' => 153.114000,
                'property_type_slug' => 'retirement-living',
                'property_condition' => 'established',
                'bedroom_option' => '2',
                'bathroom_option' => '1',
                'car_space_option' => '1',
                'land_area_sqm' => 250.00,
                'floor_area_sqm' => 110.00,
                'frontage_width_m' => 11.20,
            ],
            [
                'title' => 'Block of Units with Strong Rental Return',
                'description' => 'Multi-dwelling investment opportunity with established returns and long-term upside.',
                'status' => 'Published',
                'suburb' => 'New Farm',
                'postcode' => '4005',
                'latitude' => -27.468970,
                'longitude' => 153.044530,
                'property_type_slug' => 'block-of-units',
                'property_condition' => 'established',
                'bedroom_option' => '5_plus',
                'bathroom_option' => '3_plus',
                'car_space_option' => '3_plus',
                'land_area_sqm' => 780.00,
                'floor_area_sqm' => 620.00,
                'frontage_width_m' => 16.40,
            ],
        ];

        // Property listings belong to the Real Estate workflow only. Other
        // categories have their own business data and must never receive
        // property-management records by virtue of being companies.
        $organizations = Organization::query()
            ->whereHas('organizationType', fn ($query) => $query->where('slug', 'real-estate'))
            ->get();

        foreach ($organizations as $org) {
            $creator = User::where('organization_id', $org->id)->first();
            if (!$creator) {
                continue;
            }

            foreach ($templates as $index => $template) {
                $title = $template['title'] . ' - ' . ($index + 1);
                $propertyTypeId = $propertyTypeIds->get($template['property_type_slug'] ?? '');

                $existing = DB::table('property_listings')
                    ->where('org_id', $org->id)
                    ->where('title', $title)
                    ->first();

                $payload = [
                    'org_id' => $org->id,
                    'creator_id' => $creator->id,
                    'generated_id' => $existing?->generated_id ?? $generator->generate('properties'),
                    'avg_prop_rating' => 4.2,
                    'latitude' => $template['latitude'],
                    'longitude' => $template['longitude'],
                    'title' => $title,
                    'description' => $template['description'],
                    'status' => $this->normalizeEnum($template['status'] ?? null, self::PROPERTY_STATUSES, 'Draft'),
                    'location_verified' => ($template['status'] ?? null) === 'Published',
                    'reviewed_at' => in_array($template['status'] ?? null, ['Published', 'Archived'], true) ? now() : null,
                    'published_at' => ($template['status'] ?? null) === 'Published' ? now() : null,
                    'suburb' => $template['suburb'],
                    'postcode' => $template['postcode'],
                    'embedding' => null,
                    'property_type_id' => $propertyTypeId,
                    'property_condition' => $this->normalizeEnum($template['property_condition'] ?? null, self::PROPERTY_CONDITIONS),
                    'land_area_sqm' => $template['land_area_sqm'],
                    'floor_area_sqm' => $template['floor_area_sqm'],
                    'frontage_width_m' => $template['frontage_width_m'],
                    'bedroom_option' => $this->normalizeEnum($template['bedroom_option'] ?? null, self::BEDROOM_OPTIONS),
                    'bathroom_option' => $this->normalizeEnum($template['bathroom_option'] ?? null, self::BATHROOM_OPTIONS),
                    'car_space_option' => $this->normalizeEnum($template['car_space_option'] ?? null, self::CAR_SPACE_OPTIONS),
                    'deleted_at' => null,
                    'updated_at' => now(),
                ];

                if ($existing) {
                    DB::table('property_listings')->where('id', $existing->id)->update($payload);
                    $listingId = $existing->id;
                } else {
                    $listingId = (string) Str::uuid();
                    DB::table('property_listings')->insert(array_merge($payload, [
                        'id' => $listingId,
                        'created_at' => now(),
                    ]));
                }

                $this->seedMedia($listingId, $title, $index);
            }
        }
    }

    private function seedMedia(string $listingId, string $title, int $propertyIndex): void
    {
        if (Media::query()->where('property_listing_id', $listingId)->exists()) {
            return;
        }

        $imagePath = "property-listings/{$listingId}/images/cover-1.png";
        Storage::disk('public')->put($imagePath, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAYAAACqaXHeAAAAJUlEQVR42u3BAQ0AAADCoPdPbQ8HFAAAAAAAAAAAAAAAAAAAAAAAwH8G0gABF3a6sQAAAABJRU5ErkJggg=='
        ));

        $videoUrl = $propertyIndex === 0
            ? 'https://samplelib.com/lib/preview/mp4/sample-5s.mp4'
            : 'https://samplelib.com/lib/preview/mp4/sample-10s.mp4';

        DB::table('property_listing_media')->insert([
            [
                'id' => (string) Str::uuid(),
                'property_listing_id' => $listingId,
                'file_url' => url("/storage/{$imagePath}"),
                'media_type' => 'image',
                'is_primary' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'property_listing_id' => $listingId,
                'file_url' => $videoUrl,
                'media_type' => 'video',
                'is_primary' => false,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    private function normalizeEnum(mixed $value, array $allowed, mixed $fallback = null): mixed
    {
        if ($value === null) {
            return $fallback;
        }

        return in_array($value, $allowed, true) ? $value : $fallback;
    }
}
