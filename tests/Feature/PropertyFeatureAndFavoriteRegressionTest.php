<?php

namespace Tests\Feature;

use App\Models\Favorite;
use App\Models\PropertyFeature;
use App\Models\PropertyListing;
use App\Models\User;
use App\Services\PropertyFeatureSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PropertyFeatureAndFavoriteRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_feature_selection_can_restore_a_removed_pivot(): void
    {
        $this->seed();
        $property = PropertyListing::query()->firstOrFail();
        $features = PropertyFeature::query()->limit(2)->get();
        $service = app(PropertyFeatureSyncService::class);

        $service->sync($property, [$features[0]->id, $features[1]->id]);
        $service->sync($property, [$features[1]->id]);
        $service->sync($property, [$features[0]->id, $features[1]->id]);

        $this->assertCount(2, $property->fresh()->features);
        $this->assertDatabaseCount('property_listing_features', 2);
    }

    public function test_favorite_can_be_liked_unliked_and_liked_again(): void
    {
        $this->seed();
        $seeker = User::query()->whereHas('roles', fn ($query) => $query->where('name', 'seeker'))->firstOrFail();
        $property = PropertyListing::query()->firstOrFail();
        Sanctum::actingAs($seeker, ['seeker']);

        $payload = ['type' => 'property', 'target_id' => $property->id];
        $this->postJson('/api/seeker/favorites/toggle', $payload)->assertCreated()->assertJsonPath('data.action', 'added');
        $this->postJson('/api/seeker/favorites/toggle', $payload)->assertOk()->assertJsonPath('data.action', 'removed');
        $this->postJson('/api/seeker/favorites/toggle', $payload)->assertCreated()->assertJsonPath('data.action', 'added');

        $this->assertDatabaseCount('favorites', 1);
        $this->assertTrue(Favorite::withTrashed()->firstOrFail()->deleted_at === null);
    }
}
