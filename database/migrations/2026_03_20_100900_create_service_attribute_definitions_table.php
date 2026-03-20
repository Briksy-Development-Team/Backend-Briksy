<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_attribute_definitions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('service_group_id')->nullable();
            $table->uuid('service_id')->nullable();
            $table->string('attribute_key', 100);
            $table->string('label', 120);
            $table->enum('data_type', ['enum', 'number', 'text', 'boolean']);
            $table->json('options_json')->nullable();
            $table->string('unit', 30)->nullable();
            $table->boolean('is_required')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('service_group_id')->references('id')->on('service_groups')->cascadeOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();

            $table->unique(['service_id', 'attribute_key'], 'service_attr_unique_for_service');
            $table->index(['service_group_id', 'attribute_key'], 'service_attr_group_key_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_attribute_definitions');
    }
};

