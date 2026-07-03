<?php

use App\Models\PropertyListing;
use App\Services\DynamicIdGeneratorService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('property_listings') || !Schema::hasColumn('property_listings', 'generated_id')) {
            return;
        }

        $generator = app(DynamicIdGeneratorService::class);

        PropertyListing::query()
            ->whereNull('generated_id')
            ->orderBy('created_at')
            ->get()
            ->each(function (PropertyListing $property) use ($generator): void {
                $property->forceFill([
                    'generated_id' => $generator->generate('properties', 'PROP') ?? ('PROP-' . $property->id),
                ])->save();
            });
    }

    public function down(): void
    {
        // Intentionally left blank. Existing generated IDs should not be removed.
    }
};
