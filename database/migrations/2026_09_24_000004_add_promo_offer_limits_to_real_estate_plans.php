<?php

use App\Models\SubscriptionPlan;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            'Bronze' => 2,
            'Silver' => 5,
            'Gold' => 10,
            'Platinum' => 10,
        ];

        SubscriptionPlan::query()
            ->where('plan_family', 'property_owner')
            ->whereIn('name', array_keys($defaults))
            ->get()
            ->each(function (SubscriptionPlan $plan) use ($defaults): void {
                $features = collect($plan->features ?? []);
                $hasPromoOffers = $features->contains(
                    fn (array $feature): bool => strcasecmp((string) ($feature['name'] ?? ''), 'Promo Offers') === 0
                );

                if (!$hasPromoOffers) {
                    $features->push([
                        'name' => 'Promo Offers',
                        'enabled' => true,
                        'value' => $defaults[$plan->name],
                    ]);
                    $plan->update(['features' => $features->values()->all()]);
                }
            });
    }

    public function down(): void
    {
        // Do not remove plan features that may have been configured after
        // this migration was applied.
    }
};
