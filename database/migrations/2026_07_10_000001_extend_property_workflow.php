<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('property_listings')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE property_listings MODIFY status ENUM('Draft','Pending Review','Approved','Rejected','Published','Archived') NOT NULL"
            );
        }

        Schema::table('property_listings', function (Blueprint $table): void {
            if (!Schema::hasColumn('property_listings', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('status')->index();
            }

            if (!Schema::hasColumn('property_listings', 'reviewed_by')) {
                $table->uuid('reviewed_by')->nullable()->after('submitted_at')->index();
            }

            if (!Schema::hasColumn('property_listings', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by')->index();
            }

            if (!Schema::hasColumn('property_listings', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('reviewed_at');
            }

            if (!Schema::hasColumn('property_listings', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('rejection_reason')->index();
            }

            if (!Schema::hasColumn('property_listings', 'location_verified_by')) {
                $table->uuid('location_verified_by')->nullable()->after('location_verified')->index();
            }

            if (!Schema::hasColumn('property_listings', 'location_verified_at')) {
                $table->timestamp('location_verified_at')->nullable()->after('location_verified_by')->index();
            }
        });

        Schema::table('property_listings', function (Blueprint $table): void {
            try {
                if (Schema::hasColumn('property_listings', 'reviewed_by')) {
                    $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
                }
            } catch (\Throwable) {
            }

            try {
                if (Schema::hasColumn('property_listings', 'location_verified_by')) {
                    $table->foreign('location_verified_by')->references('id')->on('users')->nullOnDelete();
                }
            } catch (\Throwable) {
            }
        });
    }

    public function down(): void
    {
        Schema::table('property_listings', function (Blueprint $table): void {
            try {
                $table->dropForeign(['reviewed_by']);
            } catch (\Throwable) {
            }

            try {
                $table->dropForeign(['location_verified_by']);
            } catch (\Throwable) {
            }

            foreach ([
                'location_verified_at',
                'location_verified_by',
                'published_at',
                'rejection_reason',
                'reviewed_at',
                'reviewed_by',
                'submitted_at',
            ] as $column) {
                if (Schema::hasColumn('property_listings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE property_listings MODIFY status ENUM('Draft','Published','Archived') NOT NULL"
            );
        }
    }
};
