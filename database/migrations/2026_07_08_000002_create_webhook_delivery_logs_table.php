<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_delivery_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('webhook_endpoint_id')->index();
            $table->uuid('company_id')->index();
            $table->string('event', 120)->index();
            $table->string('endpoint_url', 2048);
            $table->string('deduplication_key', 191)->nullable()->index();
            $table->json('payload');
            $table->longText('response_body')->nullable();
            $table->unsignedInteger('http_status')->nullable()->index();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->enum('delivery_status', ['pending', 'retrying', 'delivered', 'failed'])->default('pending')->index();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_retry_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->foreign('webhook_endpoint_id')->references('id')->on('webhook_endpoints')->cascadeOnDelete();
            $table->foreign('company_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_delivery_logs');
    }
};
