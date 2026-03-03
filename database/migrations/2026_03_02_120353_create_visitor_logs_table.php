<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('visitor_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('viewer_id')->nullable();
            $table->uuid('org_id');
            $table->uuid('property_listing_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('viewer_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('org_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('property_listing_id')->references('id')->on('property_listing')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitor_logs');
    }
};
