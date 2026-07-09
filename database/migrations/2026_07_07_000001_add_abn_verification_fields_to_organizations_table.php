<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            if (!Schema::hasColumn('organizations', 'abn_verified')) {
                $table->boolean('abn_verified')->default(false)->after('abn');
            }

            if (!Schema::hasColumn('organizations', 'abn_verified_at')) {
                $table->timestamp('abn_verified_at')->nullable()->after('abn_verified');
            }

            if (!Schema::hasColumn('organizations', 'entity_name')) {
                $table->string('entity_name')->nullable()->after('trading_name');
            }

            if (!Schema::hasColumn('organizations', 'entity_type')) {
                $table->string('entity_type')->nullable()->after('entity_name');
            }

            if (!Schema::hasColumn('organizations', 'entity_status')) {
                $table->string('entity_status', 50)->nullable()->after('entity_type');
            }

            if (!Schema::hasColumn('organizations', 'gst_registered')) {
                $table->boolean('gst_registered')->default(false)->after('entity_status');
            }

            if (!Schema::hasColumn('organizations', 'abn_effective_from')) {
                $table->date('abn_effective_from')->nullable()->after('gst_registered');
            }

            if (!Schema::hasColumn('organizations', 'abn_raw_response')) {
                $table->json('abn_raw_response')->nullable()->after('abn_effective_from');
            }
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            if (Schema::hasColumn('organizations', 'abn_raw_response')) {
                $table->dropColumn('abn_raw_response');
            }

            if (Schema::hasColumn('organizations', 'abn_effective_from')) {
                $table->dropColumn('abn_effective_from');
            }

            if (Schema::hasColumn('organizations', 'gst_registered')) {
                $table->dropColumn('gst_registered');
            }

            if (Schema::hasColumn('organizations', 'entity_status')) {
                $table->dropColumn('entity_status');
            }

            if (Schema::hasColumn('organizations', 'entity_type')) {
                $table->dropColumn('entity_type');
            }

            if (Schema::hasColumn('organizations', 'entity_name')) {
                $table->dropColumn('entity_name');
            }

            if (Schema::hasColumn('organizations', 'abn_verified_at')) {
                $table->dropColumn('abn_verified_at');
            }

            if (Schema::hasColumn('organizations', 'abn_verified')) {
                $table->dropColumn('abn_verified');
            }
        });
    }
};
