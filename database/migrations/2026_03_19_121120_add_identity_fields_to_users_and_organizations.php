<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('mobile_number', 30)->nullable()->unique()->after('email');
            $table->timestamp('mobile_verified_at')->nullable()->after('email_verified_at');
            $table->string('display_name')->nullable()->after('name');
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('acn', 9)->nullable()->unique()->after('abn');
            $table->text('logo_url')->nullable()->after('acn');
            $table->string('brand_primary_color', 20)->nullable()->after('logo_url');
            $table->string('brand_secondary_color', 20)->nullable()->after('brand_primary_color');
            $table->unsignedInteger('licensed_staff_seats')->default(0)->after('brand_secondary_color');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn([
                'acn',
                'logo_url',
                'brand_primary_color',
                'brand_secondary_color',
                'licensed_staff_seats',
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'mobile_number',
                'mobile_verified_at',
                'display_name',
            ]);
        });
    }
};

