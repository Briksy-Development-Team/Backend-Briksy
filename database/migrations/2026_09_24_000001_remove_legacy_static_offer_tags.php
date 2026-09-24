<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $legacyLabel = implode(' ', ['BRIKSY', 'EXCLUSIVE']);

        if (Schema::hasTable('property_offers')) {
            Schema::table('property_offers', function (Blueprint $table): void {
                $table->string('tag_label', 100)->nullable()->default(null)->change();
            });

            DB::table('property_offers')
                ->where('tag_label', $legacyLabel)
                ->update(['tag_label' => null]);
        }
    }

    public function down(): void
    {
        // Static offer labels are intentionally not restored.
    }
};
