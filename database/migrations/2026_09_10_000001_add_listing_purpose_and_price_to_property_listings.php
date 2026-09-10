<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_listings', function (Blueprint $table): void {
            if (! Schema::hasColumn('property_listings', 'listing_purpose')) {
                $table->enum('listing_purpose', ['SELL', 'RENT', 'BOTH'])
                    ->default('SELL')
                    ->after('status');
                $table->index('listing_purpose');
            }

            if (! Schema::hasColumn('property_listings', 'price')) {
                $table->decimal('price', 14, 2)->nullable()->after('listing_purpose');
                $table->index('price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('property_listings', function (Blueprint $table): void {
            if (Schema::hasColumn('property_listings', 'listing_purpose')) {
                $table->dropIndex(['listing_purpose']);
                $table->dropColumn('listing_purpose');
            }
            if (Schema::hasColumn('property_listings', 'price')) {
                $table->dropIndex(['price']);
                $table->dropColumn('price');
            }
        });
    }
};
