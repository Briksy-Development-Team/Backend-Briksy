<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Organization;
use App\Models\User;
use App\Services\CollectionAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CollectionAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_favorite_always_uses_the_default_liked_collection(): void
    {
        $user = User::query()->create([
            'name' => 'Collection Test User',
            'email' => 'collection-test@example.com',
            'password_hash' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $custom = Collection::query()->create([
            'user_id' => $user->id,
            'name' => 'Investment Properties',
            'is_default' => false,
        ]);
        $target = new Organization();
        $target->id = (string) Str::uuid();

        $result = app(CollectionAssignmentService::class)->assignAfterFavorite($user, $target);

        $default = Collection::query()->where('user_id', $user->id)->where('name', 'Liked')->first();

        $this->assertNotNull($default);
        $this->assertTrue($default->is_default);
        $this->assertSame($default->id, $result['collection_id']);
        $this->assertTrue($result['collection_selection_required']);
        $this->assertDatabaseHas('collection_items', [
            'collection_id' => $default->id,
            'collectable_type' => Organization::class,
            'collectable_id' => $target->id,
        ]);
        $this->assertDatabaseMissing('collection_items', [
            'collection_id' => $custom->id,
            'collectable_id' => $target->id,
        ]);
    }
}
