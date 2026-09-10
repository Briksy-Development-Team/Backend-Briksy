<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('property_types')) {
            return;
        }

        $hasCategory = Schema::hasColumn('property_types', 'category');

        foreach ([
            'commercial' => 'Commercial',
            'office' => 'Office',
            'shop' => 'Shop',
            'warehouse' => 'Warehouse',
            'showroom' => 'Showroom',
            'industrial' => 'Industrial',
            'retail' => 'Retail',
        ] as $slug => $name) {
            $values = ['name' => $name, 'is_active' => true, 'updated_at' => now()];
            if ($hasCategory) {
                $values['category'] = 'commercial';
            }
            DB::table('property_types')->updateOrInsert(['slug' => $slug], $values);
        }
    }

    public function down(): void
    {
        // Keep taxonomy rows on rollback so existing property foreign keys and data are never destroyed.
    }
};
