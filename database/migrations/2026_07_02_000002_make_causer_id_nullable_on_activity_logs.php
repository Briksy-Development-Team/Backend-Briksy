<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || !Schema::hasTable('activity_logs') || !Schema::hasColumn('activity_logs', 'causer_id')) {
            return;
        }

        DB::statement('ALTER TABLE `activity_logs` MODIFY `causer_id` CHAR(36) NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql' || !Schema::hasTable('activity_logs') || !Schema::hasColumn('activity_logs', 'causer_id')) {
            return;
        }

        DB::statement('ALTER TABLE `activity_logs` MODIFY `causer_id` CHAR(36) NOT NULL');
    }
};
