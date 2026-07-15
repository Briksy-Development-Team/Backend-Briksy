<?php

use App\Models\User;
use App\Services\DynamicIdGeneratorService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'generated_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('generated_id')->nullable()->unique()->after('id');
            });
        }

        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'generated_id')) {
            return;
        }

        $generator = app(DynamicIdGeneratorService::class);

        User::query()
            ->whereNull('generated_id')
            ->orderBy('created_at')
            ->get()
            ->each(function (User $user) use ($generator): void {
                $user->forceFill([
                    'generated_id' => $generator->generate('users'),
                ])->save();
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'generated_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('generated_id');
            });
        }
    }
};
