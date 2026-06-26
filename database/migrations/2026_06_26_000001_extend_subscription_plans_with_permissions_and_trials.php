<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->json('permissions')->nullable()->after('features');
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->timestamp('trial_started_at')->nullable()->after('plan_id');
            $table->timestamp('trial_ends_at')->nullable()->after('trial_started_at');
            $table->string('subscription_status', 30)->default('trialing')->after('trial_ends_at');
            $table->timestamp('subscription_activated_at')->nullable()->after('subscription_status');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn([
                'trial_started_at',
                'trial_ends_at',
                'subscription_status',
                'subscription_activated_at',
            ]);
        });

        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->dropColumn('permissions');
        });
    }
};
