<?php

namespace App\Http\Controllers\Api\Seeker;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Seeker\FavoriteIndexRequest;
use App\Http\Requests\Api\Seeker\StoreFavoriteRequest;
use App\Http\Resources\Seeker\FavoriteResource;
use App\Models\Favorite;
use App\Models\Organization;
use App\Models\PropertyListing;
use Illuminate\Http\JsonResponse;

class FavoriteController extends Controller
{
    public function index(FavoriteIndexRequest $request): JsonResponse
    {
        $userId = $request->user()->id;

        $query = Favorite::query()
            ->where('user_id', $userId)
            ->with([
                'favoritable' => function ($favoritableQuery): void {
                    $favoritableQuery->with([
                        'organization.organizationType',
                        'media' => fn ($mediaQuery) => $mediaQuery->orderBy('sort_order'),
                    ]);
                },
            ])
            ->latest();

        if ($request->input('type') === 'property') {
            $query->where('favoritable_type', PropertyListing::class);
        }

        if ($request->input('type') === 'organization') {
            $query->where('favoritable_type', Organization::class);
        }

        $favorites = $query->paginate($request->perPage())->withQueryString();

        return $this->paginated(
            FavoriteResource::collection($favorites),
            $favorites,
            'Favorites retrieved successfully.'
        );
    }

    public function store(StoreFavoriteRequest $request): JsonResponse
    {
        $userId = $request->user()->id;

        $targetClass = $request->input('type') === 'property' ? PropertyListing::class : Organization::class;
        $target = $targetClass::query()->findOrFail($request->input('target_id'));

        $favorite = Favorite::query()->firstOrCreate([
            'user_id' => $userId,
            'favoritable_type' => $targetClass,
            'favoritable_id' => $target->getKey(),
        ]);

        $favorite->load('favoritable');

        return $this->created(
            new FavoriteResource($favorite),
            'Favorite added successfully.'
        );
    }

    public function toggle(StoreFavoriteRequest $request): JsonResponse
    {
        $userId = $request->user()->id;
        $targetClass = $request->input('type') === 'property' ? PropertyListing::class : Organization::class;
        $target = $targetClass::query()->findOrFail($request->input('target_id'));

        $favorite = Favorite::query()->where([
            'user_id' => $userId,
            'favoritable_type' => $targetClass,
            'favoritable_id' => $target->getKey(),
        ])->first();

        if ($favorite) {
            $favorite->delete();

            return $this->success([
                'favorite' => null,
                'action' => 'removed',
            ], 'Favorite removed successfully.');
        }

        $favorite = Favorite::query()->firstOrCreate([
            'user_id' => $userId,
            'favoritable_type' => $targetClass,
            'favoritable_id' => $target->getKey(),
        ]);

        $favorite->load('favoritable');

        return $this->created([
            'favorite' => new FavoriteResource($favorite),
            'action' => 'added',
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
