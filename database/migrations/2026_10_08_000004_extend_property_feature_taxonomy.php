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
        Schema::table('property_feature_groups', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('sort_order');
        });
        Schema::table('property_features', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('sort_order');
        });

        DB::table('property_feature_groups')->where('slug', 'indoor_features')->update(['name' => 'Indoor']);
        DB::table('property_feature_groups')->where('slug', 'outdoor_features')->update(['name' => 'Outdoor']);
        DB::table('property_feature_groups')->where('slug', 'climate_energy')->update(['name' => 'Sustainability']);
        DB::table('property_feature_groups')->where('slug', 'accessibility_sustainability')->update(['name' => 'Accessibility Where provided']);

        if (! DB::table('property_feature_groups')->where('slug', 'parking')->exists()) {
            DB::table('property_feature_groups')->insert([
                'id' => (string) Str::uuid(),
                'name' => 'Parking',
                'slug' => 'parking',
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('property_feature_groups')->where('slug', 'indoor_features')->update(['name' => 'Indoor Features']);
        DB::table('property_feature_groups')->where('slug', 'outdoor_features')->update(['name' => 'Outdoor Features']);
        DB::table('property_feature_groups')->where('slug', 'climate_energy')->update(['name' => 'Climate Control and Energy']);
        DB::table('property_feature_groups')->where('slug', 'accessibility_sustainability')->update(['name' => 'Accessibility and Sustainability']);
        DB::table('property_feature_groups')->where('slug', 'parking')->delete();
        Schema::table('property_features', fn (Blueprint $table) => $table->dropColumn('is_active'));
        Schema::table('property_feature_groups', fn (Blueprint $table) => $table->dropColumn('is_active'));
    }
};
