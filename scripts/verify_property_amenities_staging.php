<?php

require dirname(__DIR__) . '/vendor/autoload.php';

$app = require_once dirname(__DIR__) . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Read-only staging audit. Refuse production-like environments by default.
$environment = (string) config('app.env');
if (in_array(strtolower($environment), ['production', 'prod'], true)) {
    fwrite(STDERR, "Refusing to run in a production environment.\n");
    exit(2);
}

$tableCounts = [];
foreach (['property_listings', 'property_features', 'property_listing_features', 'favorites', 'collections', 'collection_items', 'inquiries'] as $table) {
    $tableCounts[$table] = Schema::hasTable($table) ? DB::table($table)->count() : null;
}

$result = [
    'environment' => $environment,
    'database' => config('database.default'),
    'tables' => $tableCounts,
    'schema' => [
        'property_features.value_type' => Schema::hasColumn('property_features', 'value_type'),
        'property_listing_features.quantity' => Schema::hasColumn('property_listing_features', 'quantity'),
        'property_listing_features.quantity_is_minimum' => Schema::hasColumn('property_listing_features', 'quantity_is_minimum'),
        'property_listings.carpet_area' => Schema::hasColumn('property_listings', 'carpet_area'),
        'property_listings.carpet_area_unit' => Schema::hasColumn('property_listings', 'carpet_area_unit'),
        'property_listings.land_area_unit' => Schema::hasColumn('property_listings', 'land_area_unit'),
        'property_listings.floor_area_unit' => Schema::hasColumn('property_listings', 'floor_area_unit'),
    ],
    'integrity' => [],
];

if (Schema::hasTable('property_listing_features')) {
    $result['integrity']['duplicate_active_pivots'] = DB::table('property_listing_features')
        ->select('property_listing_id', 'feature_id', DB::raw('COUNT(*) as count'))
        ->whereNull('deleted_at')
        ->groupBy('property_listing_id', 'feature_id')
        ->having('count', '>', 1)
        ->get();
    $result['integrity']['orphan_pivots'] = DB::table('property_listing_features as p')
        ->leftJoin('property_listings as l', 'l.id', '=', 'p.property_listing_id')
        ->leftJoin('property_features as f', 'f.id', '=', 'p.feature_id')
        ->whereNull('l.id')->orWhereNull('f.id')->count();
    $result['integrity']['negative_quantities'] = DB::table('property_listing_features')->where('quantity', '<', 0)->count();
}

if (Schema::hasTable('property_listings')) {
    $result['integrity']['invalid_area_units'] = DB::table('property_listings')
        ->where(function ($query): void {
            $query->whereNotNull('carpet_area_unit')->whereNotIn('carpet_area_unit', ['sqm', 'sqft'])
                ->orWhereNotNull('land_area_unit')->whereNotIn('land_area_unit', ['sqm', 'sqft'])
                ->orWhereNotNull('floor_area_unit')->whereNotIn('floor_area_unit', ['sqm', 'sqft']);
        })->count();
    $result['integrity']['legacy_count_rows_missing_canonical_pivot'] = [];
    if (Schema::hasTable('property_features') && Schema::hasTable('property_listing_features')) {
        foreach (['bedrooms' => 'bedroom_option', 'bathrooms' => 'bathroom_option', 'car_spaces' => 'car_space_option'] as $slug => $column) {
            $featureId = DB::table('property_features')->where('slug', $slug)->value('id');
            if (!$featureId) continue;
            $result['integrity']['legacy_count_rows_missing_canonical_pivot'][$slug] = DB::table('property_listings as l')
                ->leftJoin('property_listing_features as p', function ($join) use ($featureId): void {
                    $join->on('p.property_listing_id', '=', 'l.id')->where('p.feature_id', $featureId)->whereNull('p.deleted_at');
                })
                ->whereNotNull('l.' . $column)->whereNotIn('l.' . $column, ['', '0'])
                ->whereNull('p.id')->count();
        }
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
