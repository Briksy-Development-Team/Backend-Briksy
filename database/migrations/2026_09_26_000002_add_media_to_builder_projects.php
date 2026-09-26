<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_listing_media', function (Blueprint $table): void {
            $table->uuid('property_listing_id')->nullable()->change();
            $table->uuid('builder_project_id')->nullable()->after('property_listing_id');
            $table->foreign('builder_project_id')
                ->references('id')
                ->on('builder_projects')
                ->cascadeOnDelete();
            $table->index('builder_project_id', 'property_listing_media_project_idx');
        });
    }

    public function down(): void
    {
        Schema::table('property_listing_media', function (Blueprint $table): void {
            $table->dropForeign(['builder_project_id']);
            $table->dropIndex('property_listing_media_project_idx');
            $table->dropColumn('builder_project_id');
            $table->uuid('property_listing_id')->nullable(false)->change();
        });
    }
};
