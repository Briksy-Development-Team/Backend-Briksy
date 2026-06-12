<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable()->index();
            $table->uuid('requested_by')->nullable()->index();
            $table->uuid('plan_id')->nullable()->index();
            $table->string('company_name')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable()->index();
            $table->string('contact_phone', 30)->nullable();
            $table->string('requested_plan_name')->nullable();
            $table->string('billing_cycle', 20)->nullable();
            $table->longText('message')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending')->index();
            $table->longText('admin_notes')->nullable();
            $table->uuid('reviewed_by')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
            $table->foreign('requested_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('plan_id')->references('id')->on('subscription_plans')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_requests');
    }
};
