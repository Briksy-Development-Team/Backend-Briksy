<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_listings', function (Blueprint $table): void {
            if (!Schema::hasColumn('property_listings', 'address')) {
                $table->text('address')->nullable()->after('description');
            }
        });

        Schema::create('property_listing_media', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('property_listing_id');
            $table->text('file_url');
            $table->string('media_type', 20);
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('property_listing_id')
                ->references('id')
                ->on('property_listings')
                ->cascadeOnDelete();

            $table->index('property_listing_id', 'property_listing_media_listing_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_listing_media');
        Schema::table('property_listings', function (Blueprint $table): void {
            if (Schema::hasColumn('property_listings', 'address')) {
                $table->dropColumn('address');
            }
        });
    }
};
