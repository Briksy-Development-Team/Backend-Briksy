<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $limits = [
            'Starter' => 3,
            'Growth' => 10,
            'Elite' => 25,
            'Enterprise' => null,
        ];

        foreach (DB::table('subscription_plans')->where('plan_family', 'trades_professional')->get() as $plan) {
            $features = json_decode((string) $plan->features, true);
            $features = is_array($features) ? $features : [];
            $names = collect($features)->mapWithKeys(fn ($feature): array => [strtolower(trim((string) ($feature['name'] ?? ''))) => $feature]);
            if (!$names->has('active services')) {
                $features[] = [
                    'name' => 'Active Services',
                    'enabled' => true,
                    'value' => $limits[$plan->name] ?? null,
                ];
            }
            DB::table('subscription_plans')->where('id', $plan->id)->update(['features' => json_encode($features)]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('subscription_plans')->where('plan_family', 'trades_professional')->get() as $plan) {
            $features = json_decode((string) $plan->features, true);
            $features = collect(is_array($features) ? $features : [])
                ->reject(fn ($feature): bool => strtolower(trim((string) ($feature['name'] ?? ''))) === 'active services')
                ->values()
                ->all();
            DB::table('subscription_plans')->where('id', $plan->id)->update(['features' => json_encode($features)]);
        }
    }
};
