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
     * @param  array<string, float|int|string|null>  $quantities
     */
    public function sync(PropertyListing $listing, array $featureIds, array $quantities = []): void
    {
        $featureIds = collect($featureIds)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values();

        DB::transaction(function () use ($listing, $featureIds, $quantities): void {
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
                        'quantity' => $featureIds->contains((string) $pivot->feature_id) && array_key_exists((string) $pivot->feature_id, $quantities)
                            ? ($quantities[(string) $pivot->feature_id] === '' ? null : $quantities[(string) $pivot->feature_id])
                            : ($featureIds->contains((string) $pivot->feature_id) ? $pivot->quantity : null),
                        'quantity_is_minimum' => $featureIds->contains((string) $pivot->feature_id) && array_key_exists((string) $pivot->feature_id, $quantities) ? false : ($pivot->quantity_is_minimum ?? false),
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
                    'quantity' => array_key_exists($featureId, $quantities) && $quantities[$featureId] !== '' ? $quantities[$featureId] : null,
                    'quantity_is_minimum' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                ]);
            }
        });
    }

    /** Keep legacy count columns and canonical dynamic features aligned. */
    /**
     * Sync only legacy count fields that were part of the current write.
     * This prevents an unrelated edit from materializing canonical features
     * merely because an old listing still has legacy columns populated.
     *
     * @param  array<int, string>|null  $fields
     */
    public function syncLegacyCounts(PropertyListing $listing, ?array $fields = null): void
    {
        $mapping = ['bedrooms' => 'bedroom_option', 'bathrooms' => 'bathroom_option', 'car_spaces' => 'car_space_option'];
        if ($fields !== null) {
            $mapping = array_filter($mapping, fn (string $column): bool => in_array($column, $fields, true));
        }

        DB::transaction(function () use ($listing, $mapping): void {
            foreach ($mapping as $slug => $column) {
                $feature = DB::table('property_features')->where('slug', $slug)->first();
                if (! $feature) {
                    continue;
                }
                $raw = $listing->{$column};
                $hasValue = $raw !== null && $raw !== '' && $raw !== '0';
                $minimum = $hasValue && str_ends_with((string) $raw, '_plus');
                $quantity = $hasValue ? (int) preg_replace('/[^0-9].*$/', '', (string) $raw) : null;
                $pivot = DB::table('property_listing_features')->where('property_listing_id', $listing->id)->where('feature_id', $feature->id)->first();
                $values = ['quantity' => $quantity, 'quantity_is_minimum' => $minimum, 'deleted_at' => $hasValue ? null : now(), 'updated_at' => now()];
                if ($pivot) {
                    DB::table('property_listing_features')->where('id', $pivot->id)->update($values);
                } elseif ($hasValue) {
                    DB::table('property_listing_features')->insert(array_merge($values, [
                        'id' => (string) Str::uuid(), 'property_listing_id' => $listing->id, 'feature_id' => $feature->id,
                        'created_at' => now(),
                    ]));
                }
            }
        });
    }
}
