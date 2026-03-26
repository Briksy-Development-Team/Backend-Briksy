<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seeker_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->string('current_postcode', 10)->nullable();
            $table->decimal('preferred_budget_min', 12, 2)->nullable();
            $table->decimal('preferred_budget_max', 12, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('current_postcode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seeker_profiles');
    }
};

