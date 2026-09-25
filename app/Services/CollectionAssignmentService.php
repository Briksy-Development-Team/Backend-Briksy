<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\CollectionItem;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CollectionAssignmentService
{
    /** @return array{collection_selection_required: bool, collection_id: string|null} */
    public function assignAfterFavorite(Authenticatable $user, Model $target): array
    {
        $collections = Collection::query()->where('user_id', $user->getAuthIdentifier())->orderBy('created_at')->get();

        if ($collections->isEmpty()) {
            $collection = Collection::query()->create([
                'user_id' => $user->getAuthIdentifier(),
                'name' => 'Liked',
                'is_default' => true,
            ]);
            $this->add($collection, $target);

            return ['collection_selection_required' => false, 'collection_id' => $collection->id];
        }

        if ($collections->count() === 1) {
            $this->add($collections->first(), $target);

            return ['collection_selection_required' => false, 'collection_id' => $collections->first()->id];
        }

        return ['collection_selection_required' => true, 'collection_id' => null];
    }

    private function add(Collection $collection, Model $target): void
    {
        CollectionItem::query()->firstOrCreate([
            'collection_id' => $collection->id,
            'collectable_type' => $target::class,
            'collectable_id' => $target->getKey(),
        ], ['id' => (string) Str::uuid()]);
    }
}
