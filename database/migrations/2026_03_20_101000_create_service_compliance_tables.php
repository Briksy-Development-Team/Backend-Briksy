<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_compliance_requirements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('service_id');
            $table->string('code', 50);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
            $table->unique(['service_id', 'code']);
        });

        Schema::create('organization_service_compliances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('service_id');
            $table->uuid('requirement_id');

            $table->string('certificate_number', 100)->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->text('document_url')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'expired'])->default('pending');
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
            $table->foreign('requirement_id')->references('id')->on('service_compliance_requirements')->cascadeOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['organization_id', 'service_id'], 'org_service_compliance_lookup_idx');
            $table->unique(
                ['organization_id', 'service_id', 'requirement_id'],
                'org_service_requirement_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_service_compliances');
        Schema::dropIfExists('service_compliance_requirements');
    }
};

