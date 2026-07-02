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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable()->index();
            $table->uuid('user_id')->nullable()->index();
            $table->string('user_name')->nullable()->index();
            $table->string('user_email')->nullable()->index();
            $table->string('user_role')->nullable()->index();
            $table->string('action')->index();
            $table->string('module')->nullable()->index();
            $table->text('description')->nullable();
            $table->string('method')->nullable();
            $table->string('route')->nullable();
            $table->string('ip_address')->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
