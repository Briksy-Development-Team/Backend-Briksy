<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Seeker\AiSearchController;
use App\Models\Organization;
use App\Models\PropertyListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class AiNumericPropertyFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_numeric_filters_use_database_values_and_normalize_sqft(): void
    {
        $this->seed();
        $organization = Organization::query()->firstOrFail();
        $creator = User::query()->firstOrFail();
        $matching = $this->property($organization, $creator, 'Match', '3', '2', '2', 1250, 'sqft');
        $this->property($organization, $creator, 'Wrong bedrooms', '2', '2', '2', 1250, 'sqft');
        $this->property($organization, $creator, 'Wrong area', '3', '2', '2', 100, 'sqm');

        $controller = app(AiSearchController::class);
        $extract = new ReflectionMethod($controller, 'extractNumericFilters');
        $extract->setAccessible(true);
        $apply = new ReflectionMethod($controller, 'applyNumericFilters');
        $apply->setAccessible(true);
        $filters = $extract->invoke($controller, 'Find properties with at least 3 bedrooms and 2 bathrooms and carpet area above 1200 sqft');

        $query = PropertyListing::query();
        $apply->invoke($controller, $query, $filters);

        $this->assertSame([$matching->id], $query->pluck('id')->all());
        $this->assertSame('gte', $filters['bedrooms']['operator']);
        $this->assertSame('gt', $filters['carpet_area']['operator']);
        $this->assertSame('sqft', $filters['carpet_area']['unit']);
    }

    private function property(Organization $organization, User $creator, string $title, string $bedrooms, string $bathrooms, string $cars, float $carpetArea, string $unit): PropertyListing
    {
        return PropertyListing::query()->create([
            'org_id' => $organization->id,
            'creator_id' => $creator->id,
            'title' => $title,
            'status' => 'Published',
            'bedroom_option' => $bedrooms,
            'bathroom_option' => $bathrooms,
            'car_space_option' => $cars,
            'carpet_area' => $carpetArea,
            'carpet_area_unit' => $unit,
        ]);
    }
}
