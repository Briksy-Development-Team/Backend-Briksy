<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            if (!Schema::hasColumn('subscription_plans', 'monthly_price')) {
                $table->decimal('monthly_price', 12, 2)->nullable()->after('name');
            }
            if (!Schema::hasColumn('subscription_plans', 'yearly_price')) {
                $table->decimal('yearly_price', 12, 2)->nullable()->after('monthly_price');
            }
            if (!Schema::hasColumn('subscription_plans', 'currency')) {
                $table->string('currency', 3)->default('AUD')->after('yearly_price');
            }
            if (!Schema::hasColumn('subscription_plans', 'billing_enabled')) {
                $table->boolean('billing_enabled')->default(true)->after('currency');
            }
            if (!Schema::hasColumn('subscription_plans', 'stripe_monthly_price_id')) {
                $table->string('stripe_monthly_price_id')->nullable()->after('billing_enabled');
            }
            if (!Schema::hasColumn('subscription_plans', 'stripe_yearly_price_id')) {
                $table->string('stripe_yearly_price_id')->nullable()->after('stripe_monthly_price_id');
            }
            if (!Schema::hasColumn('subscription_plans', 'trial_days')) {
                $table->unsignedInteger('trial_days')->nullable()->after('stripe_yearly_price_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            foreach ([
                'trial_days',
                'stripe_yearly_price_id',
                'stripe_monthly_price_id',
                'billing_enabled',
                'currency',
                'yearly_price',
                'monthly_price',
            ] as $column) {
                if (Schema::hasColumn('subscription_plans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
