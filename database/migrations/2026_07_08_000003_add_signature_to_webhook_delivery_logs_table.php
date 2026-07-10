<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_delivery_logs', function (Blueprint $table): void {
            if (!Schema::hasColumn('webhook_delivery_logs', 'signature')) {
                $table->string('signature', 255)->nullable()->after('endpoint_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('webhook_delivery_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('webhook_delivery_logs', 'signature')) {
                $table->dropColumn('signature');
            }
        });
    }
};
