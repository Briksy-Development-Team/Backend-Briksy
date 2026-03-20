<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_listings', function (Blueprint $table): void {
            $table->uuid('property_type_id')->nullable()->after('status');
            $table->foreign('property_type_id')->references('id')->on('property_types')->nullOnDelete();

            $table->enum('property_condition', ['new', 'established'])->nullable()->after('property_type_id');

            $table->decimal('land_area_sqm', 10, 2)->nullable()->after('postcode');
            $table->decimal('floor_area_sqm', 10, 2)->nullable()->after('land_area_sqm');
            $table->decimal('frontage_width_m', 10, 2)->nullable()->after('floor_area_sqm');

            $table->enum('bedroom_option', ['studio', '1', '2', '3', '4', '5_plus'])->nullable()->after('frontage_width_m');
            $table->enum('bathroom_option', ['1', '2', '3_plus'])->nullable()->after('bedroom_option');
            $table->enum('car_space_option', ['1', '2', '3_plus'])->nullable()->after('bathroom_option');

            $table->index('property_type_id');
            $table->index('property_condition');
            $table->index('postcode');
            $table->index(['property_type_id', 'property_condition', 'postcode'], 'prop_listing_search_idx');
        });
    }

    public function down(): void
    {
        Schema::table('property_listings', function (Blueprint $table): void {
            $table->dropIndex('prop_listing_search_idx');
            $table->dropIndex(['property_type_id']);
            $table->dropIndex(['property_condition']);
            $table->dropIndex(['postcode']);
            $table->dropForeign(['property_type_id']);

            $table->dropColumn([
                'property_type_id',
                'property_condition',
                'land_area_sqm',
                'floor_area_sqm',
                'frontage_width_m',
                'bedroom_option',
                'bathroom_option',
                'car_space_option',
            ]);
        });
    }
};
