<?php

namespace App\Services;

use App\Models\PropertyListing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PropertyFeatureSyncService
{
    /**
     * Synchronize the soft-deletable feature pivot without violating its
     * (listing, feature) unique constraint when a previously removed feature
     * is selected again.
     *
     * @param  array<int, string>  $featureIds
     */
    public function sync(PropertyListing $listing, array $featureIds): void
    {
        $featureIds = collect($featureIds)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values();

        DB::transaction(function () use ($listing, $featureIds): void {
            $table = 'property_listing_features';
            $existing = DB::table($table)
                ->where('property_listing_id', $listing->id)
                ->get()
                ->keyBy('feature_id');

            foreach ($existing as $pivot) {
                DB::table($table)
                    ->where('id', $pivot->id)
                    ->update([
                        'deleted_at' => $featureIds->contains((string) $pivot->feature_id) ? null : now(),
                        'updated_at' => now(),
                    ]);
            }

            foreach ($featureIds as $featureId) {
                if ($existing->has($featureId)) {
                    continue;
                }

                DB::table($table)->insert([
                    'id' => (string) Str::uuid(),
                    'property_listing_id' => $listing->id,
                    'feature_id' => $featureId,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                ]);
            }
        });
    }
}
