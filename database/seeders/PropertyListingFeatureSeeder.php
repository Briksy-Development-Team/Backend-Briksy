<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PropertyListingFeatureSeeder extends Seeder
{
    public function run(): void
    {
        $featureIds = DB::table('property_features')->pluck('id');
        if ($featureIds->isEmpty()) {
            return;
        }

        $listings = DB::table('property_listings')->select('id')->get();

        foreach ($listings as $listing) {
            $selected = $featureIds->shuffle()->take(5);

            foreach ($selected as $featureId) {
                $exists = DB::table('property_listing_features')
                    ->where('property_listing_id', $listing->id)
                    ->where('feature_id', $featureId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('property_listing_features')->insert([
                    'id' => (string) Str::uuid(),
                    'property_listing_id' => $listing->id,
                    'feature_id' => $featureId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
