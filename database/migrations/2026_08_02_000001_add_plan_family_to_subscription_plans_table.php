<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            if (!Schema::hasColumn('subscription_plans', 'plan_family')) {
                $table->string('plan_family', 40)->default('property_owner')->after('name');
            }
        });

        if (Schema::hasColumn('subscription_plans', 'name')) {
            try {
                Schema::table('subscription_plans', function (Blueprint $table): void {
                    $table->dropUnique('subscription_plans_name_unique');
                });
            } catch (\Throwable) {
                // Index may not exist in older/dev databases.
            }
        }

        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->unique(['plan_family', 'name'], 'subscription_plans_family_name_unique');
            $table->index(['plan_family', 'is_active', 'popular'], 'subscription_plans_family_status_idx');
        });

        DB::table('subscription_plans')->update([
            'plan_family' => DB::raw("COALESCE(plan_family, 'property_owner')"),
        ]);
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->dropUnique('subscription_plans_family_name_unique');
            $table->dropIndex('subscription_plans_family_status_idx');
            if (Schema::hasColumn('subscription_plans', 'plan_family')) {
                $table->dropColumn('plan_family');
            }
        });

        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->unique('name', 'subscription_plans_name_unique');
        });
    }
};
