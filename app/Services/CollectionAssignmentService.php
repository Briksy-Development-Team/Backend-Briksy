<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\CollectionItem;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CollectionAssignmentService
{
    public function ensureDefault(Authenticatable $user): Collection
    {
        $collection = Collection::query()->firstOrCreate(
            [
                'user_id' => $user->getAuthIdentifier(),
                'name' => 'Liked',
            ],
            ['is_default' => true]
        );

        if (!$collection->is_default) {
            $collection->forceFill(['is_default' => true])->save();
        }

        Collection::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('id', '<>', $collection->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        return $collection;
    }

    /** @return array{collection_selection_required: bool, collection_id: string|null} */
    public function assignAfterFavorite(Authenticatable $user, Model $target): array
    {
        $default = $this->ensureDefault($user);
        $this->add($default, $target);

        $hasCustomCollections = Collection::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('id', '<>', $default->id)
            ->exists();

        return [
            'collection_selection_required' => $hasCustomCollections,
            'collection_id' => $default->id,
        ];
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
