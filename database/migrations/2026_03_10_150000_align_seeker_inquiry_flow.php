<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('contact_email')->nullable()->after('abn');
            $table->string('contact_phone', 30)->nullable()->after('contact_email');
        });

        Schema::table('inquiries', function (Blueprint $table): void {
            $table->uuid('user_id')->nullable()->change();
            $table->uuid('staff_id')->nullable()->change();
            $table->string('seeker_name')->nullable()->after('property_listing_id');
            $table->string('seeker_email')->nullable()->after('seeker_name');
            $table->string('seeker_phone', 30)->nullable()->after('seeker_email');
            $table->string('status', 20)->default('new')->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table): void {
            $table->dropColumn(['seeker_name', 'seeker_email', 'seeker_phone', 'status']);
            $table->uuid('user_id')->nullable(false)->change();
            $table->uuid('staff_id')->nullable(false)->change();
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn(['contact_email', 'contact_phone']);
        });
    }
};
