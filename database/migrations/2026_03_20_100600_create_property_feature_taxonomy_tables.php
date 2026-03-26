<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_feature_groups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 80);
            $table->string('slug', 80)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('property_features', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('group_id');
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('group_id')->references('id')->on('property_feature_groups')->cascadeOnDelete();
            $table->index('group_id');
        });

        Schema::create('property_listing_features', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('property_listing_id');
            $table->uuid('feature_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('property_listing_id')->references('id')->on('property_listings')->cascadeOnDelete();
            $table->foreign('feature_id')->references('id')->on('property_features')->cascadeOnDelete();
            $table->unique(['property_listing_id', 'feature_id'], 'prop_listing_feature_unique');
        });

        $groups = [
            'indoor_features' => 'Indoor Features',
            'outdoor_features' => 'Outdoor Features',
            'climate_energy' => 'Climate Control and Energy',
            'accessibility_sustainability' => 'Accessibility and Sustainability',
        ];

        $groupIds = [];
        $sort = 1;
        foreach ($groups as $slug => $name) {
            $id = (string) Str::uuid();
            $groupIds[$slug] = $id;

            DB::table('property_feature_groups')->insert([
                'id' => $id,
                'name' => $name,
                'slug' => $slug,
                'sort_order' => $sort++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $featuresByGroup = [
            'indoor_features' => [
                'ensuite',
                'dishwasher',
                'study',
                'built_in_robes',
                'alarm_system',
                'broadband',
                'floorboards',
                'gym',
                'rumpus_room',
                'workshop',
            ],
            'outdoor_features' => [
                'swimming_pool',
                'garage',
                'balcony',
                'outdoor_area',
                'undercover_parking',
                'shed',
                'fully_fenced',
                'outdoor_spa',
                'tennis_court',
            ],
            'climate_energy' => [
                'air_conditioning',
                'solar_panels',
                'heating',
                'fireplace',
                'high_energy_efficiency_rating',
            ],
            'accessibility_sustainability' => [
                'single_storey',
                'step_free_entry',
                'wide_doorways',
                'elevator',
                'roll_in_shower',
                'bathroom_grab_rails',
                'accessible_parking',
                'water_tank',
                'solar_hot_water',
            ],
        ];

        $featureSort = 1;
        foreach ($featuresByGroup as $groupSlug => $featureSlugs) {
            foreach ($featureSlugs as $featureSlug) {
                DB::table('property_features')->insert([
                    'id' => (string) Str::uuid(),
                    'group_id' => $groupIds[$groupSlug],
                    'name' => str_replace('_', ' ', ucwords($featureSlug, '_')),
                    'slug' => $featureSlug,
                    'sort_order' => $featureSort++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('property_listing_features');
        Schema::dropIfExists('property_features');
        Schema::dropIfExists('property_feature_groups');
    }
};

