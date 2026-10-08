<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_listings', function (Blueprint $table): void {
            $table->boolean('is_auction')->default(false)->after('transaction_status');
            $table->date('auction_date')->nullable()->after('is_auction');
            $table->time('auction_time')->nullable()->after('auction_date');
            $table->string('auction_venue', 500)->nullable()->after('auction_time');
            $table->string('auctioneer', 255)->nullable()->after('auction_venue');
            $table->string('auction_contact', 255)->nullable()->after('auctioneer');
            $table->text('auction_description')->nullable()->after('auction_contact');
        });
    }

    public function down(): void
    {
        Schema::table('property_listings', function (Blueprint $table): void {
            $table->dropColumn([
                'is_auction',
                'auction_date',
                'auction_time',
                'auction_venue',
                'auctioneer',
                'auction_contact',
                'auction_description',
            ]);
        });
    }
};
