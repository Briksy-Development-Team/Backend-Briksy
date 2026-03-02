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
        Schema::create('organizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('plan_id')->nullable();
            $table->integer('ranking_priority')->default(1);
            $table->decimal('avg_org_rating', 3, 2)->default(0);
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('business_type', ['Builder', 'Broker', 'Conveyancer', 'Landscaper']);
            $table->string('abn', 11)->unique();
            $table->string('stripe_customer_id')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('plan_id')->references('id')->on('subscription_plans')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
