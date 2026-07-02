<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table): void {
            if (!Schema::hasColumn('activity_logs', 'organization_id')) {
                $table->uuid('organization_id')->nullable()->after('id')->index();
            }

            if (!Schema::hasColumn('activity_logs', 'user_id')) {
                $table->uuid('user_id')->nullable()->after('organization_id')->index();
            }

            if (!Schema::hasColumn('activity_logs', 'user_name')) {
                $table->string('user_name')->nullable()->after('user_id')->index();
            }

            if (!Schema::hasColumn('activity_logs', 'user_email')) {
                $table->string('user_email')->nullable()->after('user_name')->index();
            }

            if (!Schema::hasColumn('activity_logs', 'user_role')) {
                $table->string('user_role')->nullable()->after('user_email')->index();
            }

            if (!Schema::hasColumn('activity_logs', 'action')) {
                $table->string('action')->nullable()->after('user_role')->index();
            }

            if (!Schema::hasColumn('activity_logs', 'module')) {
                $table->string('module')->nullable()->after('action')->index();
            }

            if (!Schema::hasColumn('activity_logs', 'description')) {
                $table->text('description')->nullable()->after('module');
            }

            if (!Schema::hasColumn('activity_logs', 'method')) {
                $table->string('method')->nullable()->after('description');
            }

            if (!Schema::hasColumn('activity_logs', 'route')) {
                $table->string('route')->nullable()->after('method');
            }

            if (!Schema::hasColumn('activity_logs', 'ip_address')) {
                $table->string('ip_address')->nullable()->after('route')->index();
            }

            if (!Schema::hasColumn('activity_logs', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip_address');
            }

            if (!Schema::hasColumn('activity_logs', 'old_values')) {
                $table->json('old_values')->nullable()->after('user_agent');
            }

            if (!Schema::hasColumn('activity_logs', 'new_values')) {
                $table->json('new_values')->nullable()->after('old_values');
            }

            if (!Schema::hasColumn('activity_logs', 'metadata')) {
                $table->json('metadata')->nullable()->after('new_values');
            }
        });

        Schema::table('activity_logs', function (Blueprint $table): void {
            if (!Schema::hasColumn('activity_logs', 'organization_id')) {
                return;
            }

            try {
                $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
            } catch (\Throwable) {
                // FK may already exist on some local databases.
            }

            try {
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            } catch (\Throwable) {
                // FK may already exist on some local databases.
            }
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table): void {
            try {
                $table->dropForeign(['organization_id']);
            } catch (\Throwable) {
            }

            try {
                $table->dropForeign(['user_id']);
            } catch (\Throwable) {
            }

            foreach ([
                'organization_id',
                'user_id',
                'user_name',
                'user_email',
                'user_role',
                'action',
                'module',
                'description',
                'method',
                'route',
                'ip_address',
                'user_agent',
                'old_values',
                'new_values',
                'metadata',
            ] as $column) {
                if (Schema::hasColumn('activity_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
