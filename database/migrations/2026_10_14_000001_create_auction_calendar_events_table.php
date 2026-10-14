<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auction_calendar_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('property_listing_id');
            $table->uuid('organization_id')->nullable();
            $table->string('title');
            $table->dateTime('starts_at');
            $table->string('venue', 500)->nullable();
            $table->json('reminders')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('property_listing_id')->references('id')->on('property_listings')->cascadeOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
            $table->unique(['user_id', 'property_listing_id']);
            $table->index('starts_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_calendar_events');
    }
};
