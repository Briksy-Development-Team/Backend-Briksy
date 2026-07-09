<?php

use App\Models\Organization;
use App\Models\PropertyListing;
use App\Models\Service;
use App\Services\DynamicIdGeneratorService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $generator = app(DynamicIdGeneratorService::class);

        if (Schema::hasTable('organizations') && Schema::hasColumn('organizations', 'generated_id')) {
            Organization::query()
                ->whereNull('generated_id')
                ->orderBy('created_at')
                ->get()
                ->each(function (Organization $organization) use ($generator): void {
                    $organization->forceFill([
                        'generated_id' => $generator->generate('organizations'),
                    ])->save();
                });
        }

        if (Schema::hasTable('property_listings') && Schema::hasColumn('property_listings', 'generated_id')) {
            PropertyListing::query()
                ->whereNull('generated_id')
                ->orderBy('created_at')
                ->get()
                ->each(function (PropertyListing $propertyListing) use ($generator): void {
                    $propertyListing->forceFill([
                        'generated_id' => $generator->generate('properties'),
                    ])->save();
                });
        }

        if (Schema::hasTable('services') && Schema::hasColumn('services', 'generated_id')) {
            Service::query()
                ->whereNull('generated_id')
                ->orderBy('created_at')
                ->get()
                ->each(function (Service $service) use ($generator): void {
                    $service->forceFill([
                        'generated_id' => $generator->generate('services'),
                    ])->save();
                });
        }
    }

    public function down(): void
    {
        // Intentionally left blank. Dynamic IDs should remain once assigned.
    }
};
