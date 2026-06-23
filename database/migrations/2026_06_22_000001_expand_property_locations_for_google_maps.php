<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_listings', function (Blueprint $table): void {
            if (!Schema::hasColumn('property_listings', 'address_line_1')) {
                $table->text('address_line_1')->nullable()->after('description');
            }

            if (!Schema::hasColumn('property_listings', 'address_line_2')) {
                $table->text('address_line_2')->nullable()->after('address_line_1');
            }

            if (!Schema::hasColumn('property_listings', 'state')) {
                $table->string('state', 50)->nullable()->after('suburb');
            }

            if (!Schema::hasColumn('property_listings', 'country')) {
                $table->string('country', 100)->nullable()->default('Australia')->after('postcode');
            }

            if (!Schema::hasColumn('property_listings', 'formatted_address')) {
                $table->text('formatted_address')->nullable()->after('country');
            }

            if (!Schema::hasColumn('property_listings', 'place_id')) {
                $table->string('place_id', 255)->nullable()->after('formatted_address');
            }

            if (!Schema::hasColumn('property_listings', 'location_verified')) {
                $table->boolean('location_verified')->default(false)->after('place_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('property_listings', function (Blueprint $table): void {
            foreach ([
                'location_verified',
                'place_id',
                'formatted_address',
                'country',
                'state',
                'address_line_2',
                'address_line_1',
            ] as $column) {
                if (Schema::hasColumn('property_listings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
