<?php

namespace App\Http\Controllers\Api\Seeker;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Seeker\AddCollectionPropertyRequest;
use App\Http\Requests\Api\Seeker\StoreCollectionRequest;
use App\Http\Requests\Api\Seeker\UpdateCollectionRequest;
use App\Http\Resources\Seeker\CollectionResource;
use App\Http\Resources\Seeker\PropertyListingResource;
use App\Models\Collection;
use App\Models\PropertyListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CollectionController extends Controller
{
    public function index(): JsonResponse
    {
        $userId = request()->user()->id;
        $propertyId = request()->query('property_id');

        $query = Collection::query()
            ->where('user_id', $userId)
            ->withCount('properties')
            ->when($propertyId, fn ($collectionQuery) => $collectionQuery->withExists([
                'properties as contains_property' => fn ($propertyQuery) => $propertyQuery->whereKey($propertyId),
            ]))
            ->orderByDesc('updated_at');

        return $this->success(CollectionResource::collection($query->get()), 'Collections retrieved successfully.');
    }

    public function store(StoreCollectionRequest $request): JsonResponse
    {
        $collection = Collection::query()->create([
            'user_id' => $request->user()->id,
            'name' => $request->validated('name'),
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
        $properties = $collection->properties()
            ->visibleToSeekers()
            ->with(['organization.organizationType', 'organization.currentSubscription.plan', 'propertyType', 'media', 'features'])
            ->withExists(['favorites as is_favourite' => fn ($favoriteQuery) => $favoriteQuery->where('user_id', request()->user()->id)])
            ->latest('collection_property.created_at')
            ->paginate(24)
            ->withQueryString();

        return $this->paginated(PropertyListingResource::collection($properties), $properties, 'Collection properties retrieved successfully.');
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

    private function owned(Collection $collection): void
    {
        abort_unless($collection->user_id === request()->user()->id, 404);
    }
}
