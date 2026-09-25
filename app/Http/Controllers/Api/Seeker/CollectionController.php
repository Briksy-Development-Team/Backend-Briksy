<?php

namespace App\Http\Controllers\Api\Seeker;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Seeker\AddCollectionPropertyRequest;
use App\Http\Requests\Api\Seeker\StoreCollectionRequest;
use App\Http\Requests\Api\Seeker\UpdateCollectionRequest;
use App\Http\Resources\Seeker\CollectionResource;
use App\Http\Resources\Seeker\CollectionItemResource;
use App\Http\Resources\Seeker\PropertyListingResource;
use App\Models\Collection;
use App\Models\Organization;
use App\Models\PropertyListing;
use App\Models\Service;
use App\Http\Requests\Api\Seeker\CollectionItemRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CollectionController extends Controller
{
    public function index(): JsonResponse
    {
        $userId = request()->user()->id;
        $propertyId = request()->query('property_id');
        $targetType = request()->query('target_type');
        $targetId = request()->query('target_id');

        $query = Collection::query()
            ->where('user_id', $userId)
            ->withCount(['items', 'properties'])
            ->when($propertyId, fn ($collectionQuery) => $collectionQuery->withExists([
                'properties as contains_property' => fn ($propertyQuery) => $propertyQuery->whereKey($propertyId),
            ]))
            ->when($targetType && $targetId, fn ($collectionQuery) => $collectionQuery->withExists([
                'items as contains_item' => fn ($itemQuery) => $itemQuery
                    ->where('collectable_type', $this->targetClass($targetType))
                    ->where('collectable_id', $targetId),
            ]))
            ->orderByDesc('updated_at');

        return $this->success(CollectionResource::collection($query->get()), 'Collections retrieved successfully.');
    }

    public function store(StoreCollectionRequest $request): JsonResponse
    {
        $collection = Collection::query()->create([
            'user_id' => $request->user()->id,
            'name' => $request->validated('name'),
            'is_default' => false,
        ]);

        return $this->created(new CollectionResource($collection->loadCount('properties')), 'Collection created successfully.');
    }

    public function update(UpdateCollectionRequest $request, Collection $collection): JsonResponse
    {
        $this->owned($collection);
        $collection->update(['name' => $request->validated('name')]);

        return $this->success(new CollectionResource($collection->loadCount('properties')), 'Collection renamed successfully.');
    }

    public function destroy(Collection $collection): JsonResponse
    {
        $this->owned($collection);
        $collection->delete();

        return $this->success(null, 'Collection deleted successfully.');
    }

    public function properties(Collection $collection)
    {
        $this->owned($collection);
        $properties = PropertyListing::query()
            ->join('collection_items', function ($join) use ($collection): void {
                $join->on('collection_items.collectable_id', '=', 'property_listings.id')
                    ->where('collection_items.collection_id', $collection->id)
                    ->where('collection_items.collectable_type', PropertyListing::class);
            })
            ->select('property_listings.*')
            ->visibleToSeekers()
            ->with(['organization.organizationType', 'organization.currentSubscription.plan', 'propertyType', 'media', 'features'])
            ->withExists(['favorites as is_favourite' => fn ($favoriteQuery) => $favoriteQuery->where('user_id', request()->user()->id)])
            ->latest('collection_items.created_at')
            ->paginate(24)
            ->withQueryString();

        return $this->paginated(PropertyListingResource::collection($properties), $properties, 'Collection properties retrieved successfully.');
    }

    public function items(Collection $collection)
    {
        $this->owned($collection);

        $items = $collection->items()
            ->with(['collectable' => function ($morphQuery): void {
                $morphQuery->morphWith([
                    PropertyListing::class => ['organization.organizationType', 'propertyType', 'media', 'features'],
                    Organization::class => ['organizationType', 'services'],
                    Service::class => ['organization', 'media'],
                ]);
            }])
            ->latest()
            ->paginate(24)
            ->withQueryString();

        return $this->paginated(CollectionItemResource::collection($items), $items, 'Collection items retrieved successfully.');
    }

    public function add(AddCollectionPropertyRequest $request, Collection $collection): JsonResponse
    {
        $this->owned($collection);
        $property = PropertyListing::query()->visibleToSeekers()->findOrFail($request->validated('property_id'));
        $collection->properties()->syncWithoutDetaching([
            $property->id => ['id' => (string) Str::uuid()],
        ]);

        return $this->success(new CollectionResource($collection->loadCount('properties')), 'Property added to Collection successfully.');
    }

    public function remove(Collection $collection, PropertyListing $property): JsonResponse
    {
        $this->owned($collection);
        $collection->properties()->detach($property->id);

        return $this->success(null, 'Property removed from Collection successfully.');
    }

    public function addItem(CollectionItemRequest $request, Collection $collection): JsonResponse
    {
        $this->owned($collection);
        $type = $request->validated('type');
        $targetClass = $this->targetClass($type);
        $targetQuery = $targetClass::query();
        if ($targetClass === PropertyListing::class) {
            $targetQuery->visibleToSeekers();
        }
        $target = $targetQuery->findOrFail($request->validated('target_id'));

        if ($target instanceof Service && ! $target->is_active) {
            abort(404);
        }

        $collection->items()->firstOrCreate([
            'collectable_type' => $targetClass,
            'collectable_id' => $target->getKey(),
        ], ['id' => (string) Str::uuid()]);

        return $this->success(new CollectionResource($collection->loadCount(['items', 'properties'])), 'Item added to Collection successfully.');
    }

    public function removeItem(Collection $collection, string $type, string $target): JsonResponse
    {
        $this->owned($collection);
        $collection->items()
            ->where('collectable_type', $this->targetClass($type))
            ->where('collectable_id', $target)
            ->delete();

        return $this->success(null, 'Item removed from Collection successfully.');
    }

    private function targetClass(string $type): string
    {
        return match ($type) {
            'property' => PropertyListing::class,
            'service' => Service::class,
            'organization' => Organization::class,
            default => abort(422, 'Invalid Collection item type.'),
        };
    }

    private function owned(Collection $collection): void
    {
        abort_unless($collection->user_id === request()->user()->id, 404);
    }
}
