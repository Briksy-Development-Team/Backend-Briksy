<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addons', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('feature_key')->index();
            $table->enum('pricing_type', ['one_time', 'monthly', 'yearly', 'usage_based'])->default('monthly');
            $table->decimal('monthly_price', 12, 2)->nullable();
            $table->decimal('yearly_price', 12, 2)->nullable();
            $table->decimal('one_time_price', 12, 2)->nullable();
            $table->string('currency', 3)->default('AUD');
            $table->json('limits')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addons');
    }
};
