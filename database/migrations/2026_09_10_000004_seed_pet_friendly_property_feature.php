<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('property_features') || ! Schema::hasTable('property_feature_groups')) {
            return;
        }

        $groupId = DB::table('property_feature_groups')->where('slug', 'outdoor_features')->value('id');
        if (! $groupId) {
            return;
        }

        $feature = DB::table('property_features')->where('slug', 'pet_friendly')->first();
        if ($feature) {
            DB::table('property_features')->where('id', $feature->id)->update([
                'group_id' => $groupId,
                'name' => 'Pet Friendly',
                'sort_order' => 100,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('property_features')->insert([
                'id' => (string) Str::uuid(),
                'group_id' => $groupId,
                'name' => 'Pet Friendly',
                'slug' => 'pet_friendly',
                'sort_order' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Preserve taxonomy and any existing property relationships on rollback.
    }
};
