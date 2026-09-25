<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('builder_projects', function (Blueprint $table): void {
            $table->timestamp('submitted_at')->nullable()->after('status');
            $table->uuid('reviewed_by')->nullable()->after('submitted_at');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('rejection_reason')->nullable()->after('reviewed_at');
            $table->timestamp('published_at')->nullable()->after('rejection_reason');
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });

        // Existing projects were already visible on the public site.
        DB::table('builder_projects')->update([
            'status' => 'Published',
            'published_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('builder_projects', function (Blueprint $table): void {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['submitted_at', 'reviewed_by', 'reviewed_at', 'rejection_reason', 'published_at']);
        });
    }
};
