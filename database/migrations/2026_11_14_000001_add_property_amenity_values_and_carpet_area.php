<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_features', function (Blueprint $table): void {
            $table->string('value_type', 20)->default('boolean')->after('sort_order');
        });

        Schema::table('property_listing_features', function (Blueprint $table): void {
            $table->decimal('quantity', 10, 2)->nullable()->after('feature_id');
            $table->boolean('quantity_is_minimum')->default(false)->after('quantity');
        });

        Schema::table('property_listings', function (Blueprint $table): void {
            $table->decimal('carpet_area', 10, 2)->nullable()->after('floor_area_sqm');
            $table->string('carpet_area_unit', 10)->default('sqm')->after('carpet_area');
            $table->string('land_area_unit', 10)->default('sqm')->after('land_area_sqm');
            $table->string('floor_area_unit', 10)->default('sqm')->after('floor_area_sqm');
        });

        $group = DB::table('property_feature_groups')->where('slug', 'indoor_features')->first();
        if (!$group) {
            $groupId = (string) Str::uuid();
            DB::table('property_feature_groups')->insert([
                'id' => $groupId,
                'name' => 'Indoor Features',
                'slug' => 'indoor_features',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $group = (object) ['id' => $groupId];
        }

        DB::table('property_features')
            ->whereIn('slug', ['ensuite'])
            ->update(['value_type' => 'number']);

        foreach ([
            ['slug' => 'bedrooms', 'name' => 'Bedrooms'],
            ['slug' => 'bathrooms', 'name' => 'Bathrooms'],
            ['slug' => 'car_spaces', 'name' => 'Car Spaces'],
            ['slug' => 'toilets', 'name' => 'Toilets'],
            ['slug' => 'living_areas', 'name' => 'Living Areas'],
        ] as $feature) {
            if (!DB::table('property_features')->where('slug', $feature['slug'])->exists()) {
                DB::table('property_features')->insert([
                    'id' => (string) Str::uuid(),
                    'group_id' => $group->id,
                    'name' => $feature['name'],
                    'slug' => $feature['slug'],
                    'sort_order' => 100,
                    'value_type' => 'number',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $featureIds = DB::table('property_features')->whereIn('slug', ['bedrooms', 'bathrooms', 'car_spaces'])->pluck('id', 'slug');
        DB::table('property_listings')->orderBy('id')->chunkById(250, function ($properties) use ($featureIds): void {
            foreach ($properties as $property) {
                foreach ([
                    'bedrooms' => $property->bedroom_option,
                    'bathrooms' => $property->bathroom_option,
                    'car_spaces' => $property->car_space_option,
                ] as $slug => $rawValue) {
                    if (!$featureIds->has($slug) || $rawValue === null || $rawValue === '') continue;
                    if (!preg_match('/^(?:studio|[0-9]+|[0-9]+_plus)$/', (string) $rawValue)) {
                        Log::warning('Property amenity legacy value could not be backfilled.', ['property_listing_id' => $property->id, 'feature' => $slug, 'value' => $rawValue]);
                        continue;
                    }
                    $minimum = str_ends_with((string) $rawValue, '_plus');
                    $quantity = $minimum ? (int) str_replace('_plus', '', (string) $rawValue) : ($rawValue === 'studio' ? 0 : (int) $rawValue);
                    if ($quantity < 0) continue;
                    $featureId = $featureIds->get($slug);
                    $existing = DB::table('property_listing_features')->where('property_listing_id', $property->id)->where('feature_id', $featureId)->first();
                    if ($existing && $existing->quantity !== null) continue;
                    if ($existing) {
                        DB::table('property_listing_features')->where('id', $existing->id)->update(['quantity' => $quantity, 'quantity_is_minimum' => $minimum, 'deleted_at' => null, 'updated_at' => now()]);
                    } else {
                        DB::table('property_listing_features')->insert(['id' => (string) Str::uuid(), 'property_listing_id' => $property->id, 'feature_id' => $featureId, 'quantity' => $quantity, 'quantity_is_minimum' => $minimum, 'created_at' => now(), 'updated_at' => now(), 'deleted_at' => null]);
                    }
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('property_listings', function (Blueprint $table): void {
            $table->dropColumn(['carpet_area', 'carpet_area_unit', 'land_area_unit', 'floor_area_unit']);
        });
        Schema::table('property_listing_features', fn (Blueprint $table) => $table->dropColumn(['quantity', 'quantity_is_minimum']));
        Schema::table('property_features', function (Blueprint $table): void {
            $table->dropColumn('value_type');
        });
    }
};
