<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('subscription_plans')) return;

        DB::table('subscription_plans')->orderBy('id')->each(function (object $plan): void {
            $features = json_decode((string) $plan->features, true) ?: [];
            $hasReels = collect($features)->contains(fn (array $feature): bool => strcasecmp((string) ($feature['name'] ?? ''), 'Reels') === 0);
            if (!$hasReels) {
                $features[] = ['name' => 'Reels', 'enabled' => false, 'value' => 0];
                DB::table('subscription_plans')->where('id', $plan->id)->update(['features' => json_encode($features)]);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('subscription_plans')) return;
        DB::table('subscription_plans')->orderBy('id')->each(function (object $plan): void {
            $features = collect(json_decode((string) $plan->features, true) ?: [])->reject(fn (array $feature): bool => strcasecmp((string) ($feature['name'] ?? ''), 'Reels') === 0)->values()->all();
            DB::table('subscription_plans')->where('id', $plan->id)->update(['features' => json_encode($features)]);
        });
    }
};
