<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('property_listings', 'transaction_status')) {
            Schema::table('property_listings', function (Blueprint $table): void {
                $table->string('transaction_status', 20)->nullable()->after('listing_purpose');
                $table->index('transaction_status');
            });
        }

        foreach (['SELL' => 'BUY', 'RENT' => 'LEASE', 'BOTH' => 'BUY'] as $purpose => $transactionStatus) {
            DB::table('property_listings')
                ->whereNull('transaction_status')
                ->whereIn('property_type_id', function ($query): void {
                    $query->select('id')
                        ->from('property_types')
                        ->where('category', 'commercial');
                })
                ->where('listing_purpose', $purpose)
                ->update(['transaction_status' => $transactionStatus]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('property_listings', 'transaction_status')) {
            Schema::table('property_listings', function (Blueprint $table): void {
                $table->dropIndex(['transaction_status']);
                $table->dropColumn('transaction_status');
            });
        }
    }
};
