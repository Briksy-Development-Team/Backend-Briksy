<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            if (!Schema::hasColumn('subscriptions', 'billing_cycle')) {
                $table->enum('billing_cycle', ['monthly', 'yearly'])->default('monthly')->after('subscription_plan_id');
            }
            if (!Schema::hasColumn('subscriptions', 'currency')) {
                $table->string('currency', 3)->default('AUD')->after('billing_cycle');
            }
            if (!Schema::hasColumn('subscriptions', 'amount')) {
                $table->decimal('amount', 12, 2)->default(0)->after('currency');
            }
            if (!Schema::hasColumn('subscriptions', 'stripe_customer_id')) {
                $table->string('stripe_customer_id')->nullable()->after('amount');
            }
            if (!Schema::hasColumn('subscriptions', 'stripe_checkout_session_id')) {
                $table->string('stripe_checkout_session_id')->nullable()->after('stripe_customer_id');
            }
            if (!Schema::hasColumn('subscriptions', 'latest_invoice_id')) {
                $table->string('latest_invoice_id')->nullable()->after('stripe_checkout_session_id');
            }
            if (!Schema::hasColumn('subscriptions', 'payment_status')) {
                $table->string('payment_status')->nullable()->after('status');
            }
            if (!Schema::hasColumn('subscriptions', 'canceled_at')) {
                $table->timestamp('canceled_at')->nullable()->after('current_period_end');
            }
        });

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE subscriptions MODIFY status VARCHAR(32) NOT NULL DEFAULT 'active'");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE subscriptions ALTER COLUMN status TYPE VARCHAR(32) USING status::text");
        }
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            foreach ([
                'payment_status',
                'latest_invoice_id',
                'stripe_checkout_session_id',
                'stripe_customer_id',
                'amount',
                'currency',
                'billing_cycle',
                'canceled_at',
            ] as $column) {
                if (Schema::hasColumn('subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
