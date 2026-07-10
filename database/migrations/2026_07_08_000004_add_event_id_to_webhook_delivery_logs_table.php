<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_delivery_logs', function (Blueprint $table): void {
            if (!Schema::hasColumn('webhook_delivery_logs', 'event_id')) {
                $table->uuid('event_id')->nullable()->after('company_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('webhook_delivery_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('webhook_delivery_logs', 'event_id')) {
                $table->dropColumn('event_id');
            }
        });
    }
};
