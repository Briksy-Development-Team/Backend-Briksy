<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('subscription_plans', 'show_price')) {
            Schema::table('subscription_plans', function (Blueprint $table): void {
                $table->boolean('show_price')->default(true)->after('billing_enabled');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('subscription_plans', 'show_price')) {
            Schema::table('subscription_plans', function (Blueprint $table): void {
                $table->dropColumn('show_price');
            });
        }
    }
};
