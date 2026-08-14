<?php

namespace Database\Seeders;

use App\Models\OrganizationType;
use Illuminate\Database\Seeder;

class OrganizationTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'real-estate' => 'Real Estate',
            'buyers-agent' => 'Buyers Agent',
            'builders' => 'Builders',
            'trades-professionals' => 'Trades & Professionals',
        ];

        foreach ($types as $slug => $name) {
            OrganizationType::withTrashed()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'deleted_at' => null]
            );
        }
    }
}
