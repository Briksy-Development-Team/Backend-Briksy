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
            if (!Schema::hasColumn('organization_types', 'module')) {
                $table->string('module', 100)->nullable()->after('slug');
            }
            if (!Schema::hasColumn('organization_types', 'display_name')) {
                $table->string('display_name', 100)->nullable()->after('module');
            }
            if (!Schema::hasColumn('organization_types', 'capability_profile')) {
                $table->string('capability_profile', 100)->nullable()->after('display_name');
            }
            if (!Schema::hasColumn('organization_types', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('capability_profile');
            }
            if (!Schema::hasColumn('organization_types', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_active');
            }

            $table->index(['module', 'is_active', 'sort_order'], 'organization_types_module_lookup_idx');
        });

        DB::table('organization_types')->whereIn('slug', ['buyers-agent', 'real-estate'])->update([
            'module' => 'Agents',
            'is_active' => true,
        ]);

        DB::table('organization_types')->where('slug', 'buyers-agent')->update([
            'display_name' => 'Buyer Agents',
            'capability_profile' => 'buyers-agent',
            'sort_order' => 1,
        ]);

        DB::table('organization_types')->where('slug', 'real-estate')->update([
            'display_name' => 'Real Estate Agents',
            'capability_profile' => 'real-estate',
            'sort_order' => 2,
        ]);
    }

    public function down(): void
    {
        Schema::table('organization_types', function (Blueprint $table): void {
            $table->dropIndex('organization_types_module_lookup_idx');
            $columns = ['module', 'display_name', 'capability_profile', 'is_active', 'sort_order'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('organization_types', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
