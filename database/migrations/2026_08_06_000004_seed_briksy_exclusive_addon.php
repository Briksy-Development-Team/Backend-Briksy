<?php

use App\Models\Addon;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $addon = Addon::withTrashed()->firstOrNew([
            'slug' => 'briksy-exclusive',
        ]);

        $addon->fill([
            'name' => 'Briksy Exclusive',
            'description' => 'Highlights the property with a BRIKSY EXCLUSIVE tag and offer section for end users.',
            'feature_key' => 'briksy_exclusive',
            'pricing_type' => 'one_time',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'one_time_price' => 0,
            'currency' => 'AUD',
            'limits' => ['tag_label' => 'BRIKSY EXCLUSIVE'],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $addon->save();

        if ($addon->trashed()) {
            $addon->restore();
        }
    }

    public function down(): void
    {
        // Keep the add-on in place; it may be referenced by existing plans.
    }
};
