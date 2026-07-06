<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('subscription_plans', 'description')) {
            Schema::table('subscription_plans', function (Blueprint $table): void {
                $table->text('description')->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('subscription_plans', 'description')) {
            Schema::table('subscription_plans', function (Blueprint $table): void {
                $table->dropColumn('description');
            });
        }
    }
};
