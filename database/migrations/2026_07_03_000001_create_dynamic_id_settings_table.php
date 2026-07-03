<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dynamic_id_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('entity_type')->unique();
            $table->string('prefix')->nullable();
            $table->string('separator', 10)->default('-');
            $table->boolean('include_year')->default(false);
            $table->boolean('include_month')->default(false);
            $table->unsignedInteger('number_padding')->default(6);
            $table->unsignedBigInteger('starting_number')->default(1);
            $table->unsignedBigInteger('current_number')->default(0);
            $table->enum('reset_frequency', ['none', 'monthly', 'yearly'])->default('none');
            $table->timestamp('last_reset_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dynamic_id_settings');
    }
};
