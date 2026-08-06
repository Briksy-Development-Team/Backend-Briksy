<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (!Schema::hasColumn('orders', 'plan_request_id')) {
                $table->uuid('plan_request_id')->nullable()->unique()->after('coupon_id');
            }
        });

        Schema::table('orders', function (Blueprint $table): void {
            try {
                if (Schema::hasColumn('orders', 'plan_request_id')) {
                    $table->foreign('plan_request_id')->references('id')->on('plan_requests')->nullOnDelete();
                }
            } catch (\Throwable) {
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            try {
                $table->dropForeign(['plan_request_id']);
            } catch (\Throwable) {
            }

            if (Schema::hasColumn('orders', 'plan_request_id')) {
                $table->dropColumn('plan_request_id');
            }
        });
    }
};
