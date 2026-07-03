<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_addons', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('plan_id');
            $table->uuid('addon_id');
            $table->unsignedInteger('included_quantity')->nullable();
            $table->boolean('is_included')->default(true);
            $table->timestamps();

            $table->unique(['plan_id', 'addon_id']);
            $table->foreign('plan_id')->references('id')->on('subscription_plans')->cascadeOnDelete();
            $table->foreign('addon_id')->references('id')->on('addons')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_addons');
    }
};
