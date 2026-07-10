<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            if (!Schema::hasColumn('services', 'service_area_geometry')) {
                $table->json('service_area_geometry')->nullable()->after('service_area');
            }
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            if (Schema::hasColumn('services', 'service_area_geometry')) {
                $table->dropColumn('service_area_geometry');
            }
        });
    }
};
