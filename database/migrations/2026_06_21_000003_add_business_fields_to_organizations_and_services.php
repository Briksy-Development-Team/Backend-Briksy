<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            if (!Schema::hasColumn('organizations', 'business_type')) {
                $table->string('business_type', 20)->default('organisation')->after('slug');
            }

            if (!Schema::hasColumn('organizations', 'trading_name')) {
                $table->string('trading_name')->nullable()->after('name');
            }

            if (!Schema::hasColumn('organizations', 'business_verification_status')) {
                $table->string('business_verification_status', 20)->default('pending')->after('is_verified');
            }

            if (!Schema::hasColumn('organizations', 'address')) {
                $table->text('address')->nullable()->after('contact_phone');
            }

            if (!Schema::hasColumn('organizations', 'state')) {
                $table->string('state', 50)->nullable()->after('address');
            }

            if (!Schema::hasColumn('organizations', 'postcode')) {
                $table->string('postcode', 10)->nullable()->after('state');
            }

            if (Schema::hasColumn('organizations', 'abn')) {
                $table->string('abn', 11)->nullable()->change();
            }
        });

        Schema::table('property_listings', function (Blueprint $table): void {
            if (!Schema::hasColumn('property_listings', 'full_address')) {
                $table->text('full_address')->nullable()->after('description');
            }
        });

        Schema::table('services', function (Blueprint $table): void {
            if (!Schema::hasColumn('services', 'organization_id')) {
                $table->uuid('organization_id')->nullable()->after('type_id');
                $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            }

            if (!Schema::hasColumn('services', 'title')) {
                $table->string('title')->nullable()->after('name');
            }

            if (!Schema::hasColumn('services', 'category')) {
                $table->string('category')->nullable()->after('title');
            }

            if (!Schema::hasColumn('services', 'service_area')) {
                $table->string('service_area')->nullable()->after('description');
            }

            if (!Schema::hasColumn('services', 'rate_from')) {
                $table->decimal('rate_from', 12, 2)->nullable()->after('service_area');
            }

            if (!Schema::hasColumn('services', 'rate_to')) {
                $table->decimal('rate_to', 12, 2)->nullable()->after('rate_from');
            }
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            if (Schema::hasColumn('services', 'rate_to')) {
                $table->dropColumn('rate_to');
            }

            if (Schema::hasColumn('services', 'rate_from')) {
                $table->dropColumn('rate_from');
            }

            if (Schema::hasColumn('services', 'service_area')) {
                $table->dropColumn('service_area');
            }

            if (Schema::hasColumn('services', 'title')) {
                $table->dropColumn('title');
            }

            if (Schema::hasColumn('services', 'category')) {
                $table->dropColumn('category');
            }

            if (Schema::hasColumn('services', 'organization_id')) {
                $table->dropForeign(['organization_id']);
                $table->dropColumn('organization_id');
            }
        });

        Schema::table('property_listings', function (Blueprint $table): void {
            if (Schema::hasColumn('property_listings', 'full_address')) {
                $table->dropColumn('full_address');
            }
        });

        Schema::table('organizations', function (Blueprint $table): void {
            if (Schema::hasColumn('organizations', 'postcode')) {
                $table->dropColumn('postcode');
            }

            if (Schema::hasColumn('organizations', 'state')) {
                $table->dropColumn('state');
            }

            if (Schema::hasColumn('organizations', 'address')) {
                $table->dropColumn('address');
            }

            if (Schema::hasColumn('organizations', 'business_verification_status')) {
                $table->dropColumn('business_verification_status');
            }

            if (Schema::hasColumn('organizations', 'trading_name')) {
                $table->dropColumn('trading_name');
            }

            if (Schema::hasColumn('organizations', 'business_type')) {
                $table->dropColumn('business_type');
            }
        });
    }
};
