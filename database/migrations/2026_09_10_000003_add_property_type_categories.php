<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('property_types')) {
            return;
        }

        if (! Schema::hasColumn('property_types', 'category')) {
            Schema::table('property_types', function (Blueprint $table): void {
                $table->string('category', 30)->default('residential')->after('slug');
                $table->index('category');
            });
        }

        DB::table('property_types')
            ->whereIn('slug', ['commercial', 'office', 'shop', 'warehouse', 'showroom', 'industrial', 'retail'])
            ->update(['category' => 'commercial']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('property_types', 'category')) {
            Schema::table('property_types', function (Blueprint $table): void {
                $table->dropIndex(['category']);
                $table->dropColumn('category');
            });
        }
    }
};
