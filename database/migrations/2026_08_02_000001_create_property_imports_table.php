<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_imports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('org_id')->nullable()->index();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('module', 50)->default('property');
            $table->string('status', 30)->default('draft');
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('stored_disk', 30)->default('local');
            $table->string('file_type', 20);
            $table->json('source_columns')->nullable();
            $table->json('mapping')->nullable();
            $table->json('preview')->nullable();
            $table->json('summary')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->unsignedInteger('missing_required_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('error_report_path')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_imports');
    }
};
