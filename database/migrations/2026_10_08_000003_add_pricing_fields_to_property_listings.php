<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_listings', function (Blueprint $table): void {
            $table->string('pricing_type', 20)->default('fixed')->after('price');
            $table->decimal('price_min', 14, 2)->nullable()->after('pricing_type');
            $table->decimal('price_max', 14, 2)->nullable()->after('price_min');
        });
    }

    public function down(): void
    {
        Schema::table('property_listings', function (Blueprint $table): void {
            $table->dropColumn(['pricing_type', 'price_min', 'price_max']);
        });
    }
};
