<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table): void {
            $table->uuid('organization_id')->nullable()->change();
            $table->uuid('staff_id')->nullable()->change();
            if (!Schema::hasColumn('inquiries', 'plan_id')) {
                $table->uuid('plan_id')->nullable()->after('organization_id');
            }
            if (!Schema::hasColumn('inquiries', 'company_name')) {
                $table->string('company_name', 200)->nullable()->after('seeker_phone');
            }
        });

        Schema::create('checkout_invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('inquiry_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->uuid('organization_id')->nullable();
            $table->uuid('plan_id');
            $table->uuid('created_by')->nullable();
            $table->string('token', 128)->unique();
            $table->string('status', 24)->default('active')->index();
            $table->string('billing_cycle', 20)->default('monthly');
            $table->json('addons')->nullable();
            $table->string('stripe_checkout_session_id')->nullable()->index();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->foreign('inquiry_id')->references('id')->on('inquiries')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
            $table->foreign('plan_id')->references('id')->on('subscription_plans')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_invitations');
        Schema::table('inquiries', function (Blueprint $table): void {
            if (Schema::hasColumn('inquiries', 'company_name')) $table->dropColumn('company_name');
            if (Schema::hasColumn('inquiries', 'plan_id')) $table->dropColumn('plan_id');
            $table->uuid('organization_id')->nullable(false)->change();
            $table->uuid('staff_id')->nullable(false)->change();
        });
    }
};
