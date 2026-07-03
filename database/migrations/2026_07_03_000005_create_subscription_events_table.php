<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable()->index();
            $table->uuid('subscription_id')->nullable()->index();
            $table->string('event_type')->index();
            $table->string('stripe_event_id')->nullable()->unique();
            $table->json('payload')->nullable();
            $table->string('status')->default('processed');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
    }
};
