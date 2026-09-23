<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('subscription_plans')
            ->where('plan_family', 'trades_professional')
            ->whereIn('name', ['Starter', 'Growth', 'Elite'])
            ->get(['id', 'features'])
            ->each(function (object $plan): void {
                $features = json_decode((string) $plan->features, true);
                $features = is_array($features) ? $features : [];
                $required = ['Verified Badge', 'Business Profile'];

                foreach ($required as $name) {
                    $index = collect($features)->search(
                        fn (mixed $feature): bool => is_array($feature)
                            && strcasecmp((string) ($feature['name'] ?? ''), $name) === 0
                    );

                    if ($index === false) {
                        $features[] = ['name' => $name, 'enabled' => true, 'value' => null];
                    } else {
                        $features[$index]['name'] = $name;
                        $features[$index]['enabled'] = true;
                        $features[$index]['value'] = $features[$index]['value'] ?? null;
                    }
                }

                DB::table('subscription_plans')->where('id', $plan->id)->update([
                    'features' => json_encode(array_values($features)),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // Do not disable these approved entitlements on rollback. Existing
        // organizations must retain the configured feature state.
    }
};
