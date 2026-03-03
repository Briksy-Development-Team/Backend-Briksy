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
        Schema::create('service_group_services', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('service_group_id');
            $table->uuid('service_id');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('service_group_id')->references('id')->on('service_groups')->cascadeOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
            $table->unique(['service_group_id', 'service_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_group_services');
    }
};
