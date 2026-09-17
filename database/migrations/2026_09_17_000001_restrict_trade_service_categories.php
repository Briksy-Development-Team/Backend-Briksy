<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tradeTypeId = DB::table('organization_types')->where('slug', 'trades-professionals')->value('id');
        if (! $tradeTypeId) {
            return;
        }

        $categories = config('service_categories', []);
        $allowedSlugs = array_column($categories, 'slug');

        foreach ($categories as $category) {
            DB::table('services')
                ->where('type_id', $tradeTypeId)
                ->where(function ($query) use ($category): void {
                    $query->whereRaw('LOWER(category) = ?', [strtolower($category['slug'])])
                        ->orWhereRaw('LOWER(category) = ?', [strtolower($category['label'])]);
                })
                ->update(['category' => $category['label'], 'is_active' => true]);

            DB::table('services')
                ->where('type_id', $tradeTypeId)
                ->where('slug', $category['slug'])
                ->update(['category' => $category['label'], 'is_active' => true]);
        }

        DB::table('services')
            ->where('type_id', $tradeTypeId)
            ->whereNotIn('slug', $allowedSlugs)
            ->update(['is_active' => false]);
    }

    public function down(): void
    {
        // Category cleanup is intentionally not reversed; restoring obsolete
        // services would make them visible again in the public marketplace.
    }
};
