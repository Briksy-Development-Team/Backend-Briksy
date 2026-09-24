<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            'Bronze' => [5, 0, false],
            'Silver' => [15, 3, true],
            'Gold' => [25, 5, true],
            'Platinum' => [50, 10, true],
        ];

        DB::table('subscription_plans')
            ->where('plan_family', 'property_owner')
            ->get(['id', 'name', 'features'])
            ->each(function (object $plan) use ($defaults): void {
                [$imageLimit, $videoLimit, $videosEnabled] = $defaults[$plan->name] ?? [null, null, null];
                if ($imageLimit === null) {
                    return;
                }

                $features = is_string($plan->features)
                    ? (json_decode($plan->features, true) ?: [])
                    : ($plan->features ?: []);
                $names = collect($features)->pluck('name')->map(fn ($name) => strtolower(trim((string) $name)));

                if (!$names->contains('property images')) {
                    $features[] = ['name' => 'Property Images', 'enabled' => true, 'value' => $imageLimit];
                }
                if (!$names->contains('property videos')) {
                    $features[] = ['name' => 'Property Videos', 'enabled' => $videosEnabled, 'value' => $videoLimit];
                }

                DB::table('subscription_plans')->where('id', $plan->id)->update([
                    'features' => json_encode(array_values($features)),
                ]);
            });
    }

    public function down(): void
    {
        // Existing configured plan features are intentionally preserved.
    }
};
