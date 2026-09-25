<?php

namespace App\Http\Controllers\Api\Seeker;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Seeker\FavoriteIndexRequest;
use App\Http\Requests\Api\Seeker\StoreFavoriteRequest;
use App\Http\Resources\Seeker\FavoriteResource;
use App\Models\Favorite;
use App\Models\Organization;
use App\Models\PropertyListing;
use App\Models\Service;
use App\Services\CollectionAssignmentService;
use Illuminate\Http\JsonResponse;

class FavoriteController extends Controller
{
    public function index(FavoriteIndexRequest $request): JsonResponse
    {
        $userId = $request->user()->id;

        $query = Favorite::query()
            ->where('user_id', $userId)
            ->with('favoritable')
            ->latest();

        if ($request->input('type') === 'property') {
            $query->where('favoritable_type', PropertyListing::class);
        }

        if ($request->input('type') === 'organization') {
            $query->where('favoritable_type', Organization::class);
        }

        if ($request->input('type') === 'service') {
            $query->where('favoritable_type', Service::class);
        }

        $favorites = $query->paginate($request->perPage())->withQueryString();

        return $this->paginated(
            FavoriteResource::collection($favorites),
            $favorites,
            'Favorites retrieved successfully.'
        );
    }

    public function store(StoreFavoriteRequest $request, CollectionAssignmentService $collectionAssignmentService): JsonResponse
    {
        $userId = $request->user()->id;

        $targetClass = match ($request->input('type')) {
            'property' => PropertyListing::class,
            'service' => Service::class,
            default => Organization::class,
        };
        $target = $targetClass::query()->findOrFail($request->input('target_id'));

        $favorite = Favorite::withTrashed()->where([
            'user_id' => $userId,
            'favoritable_type' => $targetClass,
            'favoritable_id' => $target->getKey(),
        ])->first();

        if ($favorite?->trashed()) {
            $favorite->restore();
        }

        $favorite ??= Favorite::query()->create([
            'user_id' => $userId,
            'favoritable_type' => $targetClass,
            'favoritable_id' => $target->getKey(),
        ]);

        $favorite->load('favoritable');
        $collection = $collectionAssignmentService->assignAfterFavorite($request->user(), $target);

        return $this->created(
            ['favorite' => (new FavoriteResource($favorite))->resolve($request), ...$collection],
            'Favorite added successfully.'
        );
    }

    public function toggle(StoreFavoriteRequest $request, CollectionAssignmentService $collectionAssignmentService): JsonResponse
    {
        $userId = $request->user()->id;
        $targetClass = match ($request->input('type')) {
            'property' => PropertyListing::class,
            'service' => Service::class,
            default => Organization::class,
        };
        $target = $targetClass::query()->findOrFail($request->input('target_id'));

        $favorite = Favorite::withTrashed()->where([
            'user_id' => $userId,
            'favoritable_type' => $targetClass,
            'favoritable_id' => $target->getKey(),
        ])->first();

        if ($favorite) {
            if ($favorite->trashed()) {
                $favorite->restore();
                $favorite->load('favoritable');
                $collection = $collectionAssignmentService->assignAfterFavorite($request->user(), $target);

                return $this->created([
                    'favorite' => new FavoriteResource($favorite),
                    'action' => 'added',
                    ...$collection,
                ], 'Favorite added successfully.');
            }

            $favorite->delete();

            return $this->success([
                'favorite' => null,
                'action' => 'removed',
            ], 'Favorite removed successfully.');
        }

        $favorite = Favorite::query()->create([
            'user_id' => $userId,
            'favoritable_type' => $targetClass,
            'favoritable_id' => $target->getKey(),
        ]);

        $favorite->load('favoritable');
        $collection = $collectionAssignmentService->assignAfterFavorite($request->user(), $target);

        return $this->created([
            'favorite' => new FavoriteResource($favorite),
            'action' => 'added',
            ...$collection,
        ], 'Favorite added successfully.');
    }

    public function destroy(Favorite $favorite): JsonResponse
    {
        $userId = request()->user()->id;

        if ($favorite->user_id !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to remove this favorite.',
            ], 403);
        }

        $favorite->delete();

        return $this->success(
            null,
            'Favorite removed successfully.'
        );
    }
}
