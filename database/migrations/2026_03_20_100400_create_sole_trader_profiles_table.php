<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sole_trader_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->uuid('organization_id')->nullable();

            $table->string('trading_name')->nullable();
            $table->string('abn', 11)->nullable();
            $table->string('trade_license_number', 100)->nullable();

            $table->string('primary_service_postcode', 10)->nullable();
            $table->unsignedInteger('service_radius_km')->nullable();

            $table->text('profile_image_url')->nullable();
            $table->text('professional_bio')->nullable();

            $table->string('public_liability_insurer')->nullable();
            $table->string('policy_number')->nullable();
            $table->date('policy_expiry_date')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();

            $table->index('primary_service_postcode');
            $table->index('abn');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sole_trader_profiles');
    }
};

