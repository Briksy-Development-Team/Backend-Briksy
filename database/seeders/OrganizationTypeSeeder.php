<?php

namespace Database\Seeders;

use App\Models\OrganizationType;
use Illuminate\Database\Seeder;

class OrganizationTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'real-estate' => 'Real Estate Agency',
            'property-management' => 'Property Management',
            'aged-care' => 'Aged Care',
            'home-services' => 'Home Services',
        ];

        foreach ($types as $slug => $name) {
            OrganizationType::withTrashed()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'deleted_at' => null]
            );
        }
    }
}
