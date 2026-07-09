<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_delivery_logs', function (Blueprint $table): void {
            if (!Schema::hasColumn('webhook_delivery_logs', 'dead_lettered_at')) {
                $table->timestamp('dead_lettered_at')->nullable()->after('failed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('webhook_delivery_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('webhook_delivery_logs', 'dead_lettered_at')) {
                $table->dropColumn('dead_lettered_at');
            }
        });
    }
};
