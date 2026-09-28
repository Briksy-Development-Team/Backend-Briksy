<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->dropUnique('subscription_plans_family_name_unique');
            $table->unique(
                ['plan_family', 'name', 'deleted_at'],
                'subscription_plans_family_name_deleted_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->dropUnique('subscription_plans_family_name_deleted_unique');
            $table->unique(['plan_family', 'name'], 'subscription_plans_family_name_unique');
        });
    }
};
