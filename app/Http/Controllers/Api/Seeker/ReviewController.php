<?php

namespace App\Http\Controllers\Api\Seeker;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Seeker\ReviewIndexRequest;
use App\Http\Requests\Api\Seeker\StoreReviewRequest;
use App\Http\Resources\Seeker\ReviewResource;
use App\Models\Review;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;

class ReviewController extends Controller
{
    public function index(ReviewIndexRequest $request): JsonResponse
    {
        $userId = $this->resolveUserId($request);

        if ($userId === null) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $query = Review::query()->where('user_id', $userId)->latest();

        if ($request->filled('organization_id')) {
            $query->where('organization_id', $request->input('organization_id'));
        }

        if ($request->filled('property_listing_id')) {
            $query->where('property_listing_id', $request->input('property_listing_id'));
        }

        ApiQueryBuilder::applySort($query, $request->sort(), $request->direction(), $request->allowedSorts(), 'created_at');

        $reviews = $query->paginate($request->perPage())->withQueryString();

        return $this->paginated(
            ReviewResource::collection($reviews),
            $reviews,
            'Reviews retrieved successfully.'
        );
    }

    public function store(StoreReviewRequest $request): JsonResponse
    {
        $userId = $this->resolveUserId($request);

        if ($userId === null) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $review = Review::query()->create([
            'user_id' => $userId,
            'organization_id' => $request->input('organization_id'),
            'property_listing_id' => $request->input('property_listing_id'),
            'rating' => $request->input('rating'),
            'comment' => $request->input('comment'),
        ]);

        return $this->created(
            new ReviewResource($review),
            'Review created successfully.'
        );
    }

    protected function resolveUserId(ReviewIndexRequest|StoreReviewRequest|\Illuminate\Http\Request $request): ?string
    {
        return $request->user()?->id ?? $request->input('user_id');
    }
}
