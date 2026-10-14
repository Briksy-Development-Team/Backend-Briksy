<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AuctionPropertySeeder extends Seeder
{
    public function run(): void
    {
        $property = DB::table('property_listings')
            ->where('title', 'Boutique Showroom and Trade Outlet - harborview-realty')
            ->first();

        if (!$property) {
            $this->command?->warn('Auction test property was not found. Run CommercialPropertySeeder first.');
            return;
        }

        DB::table('property_listings')->where('id', $property->id)->update([
            'is_auction' => true,
            'auction_date' => '2026-10-17',
            'auction_time' => '11:00:00',
            'auction_venue' => 'Harborview Realty, 1234 Elm Street, Richmond, VIC 3121, Australia',
            'auctioneer' => 'Harborview Realty Auctions',
            'auction_contact' => '+61 400 000 000',
            'auction_description' => 'Test auction listing for the Briksy calendar and registration flow.',
            'listing_purpose' => 'SELL',
            'transaction_status' => 'BUY',
            'updated_at' => now(),
        ]);

        $this->command?->info("Auction test property ready: {$property->id}");
    }
}
