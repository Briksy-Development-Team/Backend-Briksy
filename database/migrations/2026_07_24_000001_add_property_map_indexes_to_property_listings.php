<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_listings', function (Blueprint $table): void {
            $table->index('latitude', 'property_listings_latitude_idx');
            $table->index('longitude', 'property_listings_longitude_idx');
            $table->index('status', 'property_listings_status_idx');
            $table->index('org_id', 'property_listings_org_id_idx');
            $table->index(['org_id', 'status'], 'property_listings_org_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('property_listings', function (Blueprint $table): void {
            $table->dropIndex('property_listings_org_status_idx');
            $table->dropIndex('property_listings_org_id_idx');
            $table->dropIndex('property_listings_status_idx');
            $table->dropIndex('property_listings_longitude_idx');
            $table->dropIndex('property_listings_latitude_idx');
        });
    }
};
