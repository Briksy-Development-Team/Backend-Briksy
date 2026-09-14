<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_media', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('service_id');
            $table->text('file_url');
            $table->string('media_type', 20);
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
            $table->index('service_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_media');
    }
};
