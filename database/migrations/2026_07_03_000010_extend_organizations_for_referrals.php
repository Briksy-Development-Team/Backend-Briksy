<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\DynamicIdGeneratorService;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('organizations')) {
            return;
        }

        Schema::table('organizations', function (Blueprint $table): void {
            if (!Schema::hasColumn('organizations', 'referral_code')) {
                $table->string('referral_code')->nullable()->unique()->after('generated_id');
            }

            if (!Schema::hasColumn('organizations', 'referred_by_organization_id')) {
                $table->uuid('referred_by_organization_id')->nullable()->after('referral_code')->index();
            }
        });

        Schema::table('organizations', function (Blueprint $table): void {
            if (Schema::hasColumn('organizations', 'referred_by_organization_id')) {
                try {
                    $table->foreign('referred_by_organization_id')->references('id')->on('organizations')->nullOnDelete();
                } catch (\Throwable) {
                }
            }
        });

        $generator = app(DynamicIdGeneratorService::class);

        DB::table('organizations')
            ->select(['id', 'referral_code'])
            ->orderBy('created_at')
            ->get()
            ->each(function ($organization) use ($generator): void {
                if (!empty($organization->referral_code)) {
                    return;
                }

                $code = $generator->generate('referrals');

                DB::table('organizations')->where('id', $organization->id)->update([
                    'referral_code' => $code,
                ]);
            });
    }

    public function down(): void
    {
        if (!Schema::hasTable('organizations')) {
            return;
        }

        Schema::table('organizations', function (Blueprint $table): void {
            try {
                $table->dropForeign(['referred_by_organization_id']);
            } catch (\Throwable) {
            }

            foreach (['referral_code', 'referred_by_organization_id'] as $column) {
                if (Schema::hasColumn('organizations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
