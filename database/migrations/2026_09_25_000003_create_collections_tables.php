<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('name', 100);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'name'], 'collections_user_name_unique');
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('collection_property', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('collection_id');
            $table->uuid('property_id');
            $table->timestamps();

            $table->foreign('collection_id')->references('id')->on('collections')->cascadeOnDelete();
            $table->foreign('property_id')->references('id')->on('property_listings')->cascadeOnDelete();
            $table->unique(['collection_id', 'property_id'], 'collection_property_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_property');
        Schema::dropIfExists('collections');
    }
};
