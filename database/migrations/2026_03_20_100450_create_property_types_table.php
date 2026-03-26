<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_types', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        $types = [
            'retirement-living' => 'Retirement Living',
            'house' => 'House',
            'land' => 'Land',
            'townhouse' => 'Townhouse',
            'acreage' => 'Acreage',
            'apartment-unit' => 'Apartment and Unit',
            'rural' => 'Rural',
            'villa' => 'Villa',
            'block-of-units' => 'Block Of Units',
        ];

        $sort = 1;
        foreach ($types as $slug => $name) {
            DB::table('property_types')->insert([
                'id' => (string) Str::uuid(),
                'name' => $name,
                'slug' => $slug,
                'sort_order' => $sort++,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('property_types');
    }
};

