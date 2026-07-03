<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_addons', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('subscription_id');
            $table->uuid('addon_id');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('amount', 12, 2)->default(0);
            $table->enum('billing_cycle', ['monthly', 'yearly', 'one_time', 'usage_based'])->default('monthly');
            $table->string('stripe_price_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('subscription_id')->references('id')->on('subscriptions')->cascadeOnDelete();
            $table->foreign('addon_id')->references('id')->on('addons')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_addons');
    }
};
