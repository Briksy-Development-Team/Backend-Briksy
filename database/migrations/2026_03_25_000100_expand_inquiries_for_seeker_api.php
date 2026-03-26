<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table): void {
            $table->uuid('user_id')->nullable()->change();

            if (!Schema::hasColumn('inquiries', 'seeker_name')) {
                $table->string('seeker_name', 120)->nullable()->after('message');
            }

            if (!Schema::hasColumn('inquiries', 'seeker_email')) {
                $table->string('seeker_email', 150)->nullable()->after('seeker_name');
            }

            if (!Schema::hasColumn('inquiries', 'seeker_phone')) {
                $table->string('seeker_phone', 30)->nullable()->after('seeker_email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table): void {
            if (Schema::hasColumn('inquiries', 'seeker_phone')) {
                $table->dropColumn('seeker_phone');
            }

            if (Schema::hasColumn('inquiries', 'seeker_email')) {
                $table->dropColumn('seeker_email');
            }

            if (Schema::hasColumn('inquiries', 'seeker_name')) {
                $table->dropColumn('seeker_name');
            }

            $table->uuid('user_id')->nullable(false)->change();
        });
    }
};
