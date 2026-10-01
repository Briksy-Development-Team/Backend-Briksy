<?php

use App\Models\OrganizationType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Existing real-estate organizations are property-owning agencies in
        // the current data set. Keep their type ID and slug; only correct the
        // metadata that identifies the business module.
        OrganizationType::withTrashed()->where('slug', 'real-estate')->update([
            'name' => 'Real Estate',
            'display_name' => 'Real Estate',
            'module' => 'Real Estate',
            'capability_profile' => 'real-estate-agency',
            'is_active' => true,
            'sort_order' => 1,
            'deleted_at' => null,
        ]);

        OrganizationType::withTrashed()->updateOrCreate(
            ['slug' => 'real-estate-agent'],
            [
                'name' => 'Real Estate Agents',
                'display_name' => 'Real Estate Agents',
                'module' => 'Agents',
                'capability_profile' => 'real-estate-agent',
                'is_active' => true,
                'sort_order' => 2,
                'deleted_at' => null,
            ]
        );

        OrganizationType::withTrashed()->where('slug', 'buyers-agent')->update([
            'name' => 'Buyer Agents',
            'display_name' => 'Buyer Agents',
            'module' => 'Agents',
            'capability_profile' => 'buyers-agent',
            'is_active' => true,
            'sort_order' => 1,
            'deleted_at' => null,
        ]);

        OrganizationType::withTrashed()->where('slug', 'builders')->update([
            'display_name' => 'Builders',
            'module' => 'Builders',
            'capability_profile' => 'builder',
            'is_active' => true,
            'sort_order' => 1,
            'deleted_at' => null,
        ]);

        OrganizationType::withTrashed()->where('slug', 'trades-professionals')->update([
            'display_name' => 'Trades & Professionals',
            'module' => 'Trades & Professionals',
            'capability_profile' => 'trades',
            'is_active' => true,
            'sort_order' => 1,
            'deleted_at' => null,
        ]);
    }

    public function down(): void
    {
        $agentType = OrganizationType::withTrashed()->where('slug', 'real-estate-agent')->first();
        if ($agentType && $agentType->organizations()->exists()) {
            throw new RuntimeException('Cannot roll back real-estate-agent while organizations reference it.');
        }
        $agentType?->delete();

        OrganizationType::withTrashed()->where('slug', 'real-estate')->update([
            'name' => 'Real Estate',
            'display_name' => 'Real Estate Agents',
            'module' => 'Agents',
            'capability_profile' => 'real-estate',
            'sort_order' => 2,
            'deleted_at' => null,
        ]);
    }
};
