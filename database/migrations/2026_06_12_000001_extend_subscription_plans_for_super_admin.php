<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->integer('price')->default(0)->after('stripe_price_id');
            $table->integer('property_limit')->default(0)->after('price');
            $table->boolean('popular')->default(false)->after('property_limit');
            $table->json('features')->nullable()->after('popular');
            $table->boolean('is_active')->default(true)->after('features');

            $table->index(['is_active', 'popular', 'created_at'], 'subscription_plans_status_popular_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->dropIndex('subscription_plans_status_popular_created_idx');
            $table->dropColumn(['price', 'property_limit', 'popular', 'features', 'is_active']);
        });
    }
};
