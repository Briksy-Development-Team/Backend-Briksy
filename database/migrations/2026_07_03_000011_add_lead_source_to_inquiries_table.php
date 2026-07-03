<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('inquiries')) {
            return;
        }

        Schema::table('inquiries', function (Blueprint $table): void {
            if (!Schema::hasColumn('inquiries', 'lead_source')) {
                $table->string('lead_source', 80)->nullable()->after('staff_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('inquiries')) {
            return;
        }

        Schema::table('inquiries', function (Blueprint $table): void {
            if (Schema::hasColumn('inquiries', 'lead_source')) {
                $table->dropColumn('lead_source');
            }
        });
    }
};
