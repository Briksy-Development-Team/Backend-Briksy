<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_types', function (Blueprint $table): void {
            if (!Schema::hasColumn('organization_types', 'plan_family')) {
                $table->string('plan_family', 40)->nullable()->after('capability_profile');
                $table->index(['module', 'plan_family'], 'organization_types_module_plan_family_idx');
            }
        });

        DB::table('organization_types')
            ->where('module', 'Agents')
            ->whereNull('plan_family')
            ->update(['plan_family' => 'buyers_agent']);
    }

    public function down(): void
    {
        Schema::table('organization_types', function (Blueprint $table): void {
            if (Schema::hasColumn('organization_types', 'plan_family')) {
                $table->dropIndex('organization_types_module_plan_family_idx');
                $table->dropColumn('plan_family');
            }
        });
    }
};
