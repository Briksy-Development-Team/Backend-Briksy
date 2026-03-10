<?php

namespace App\Http\Controllers\Api\Seeker;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Seeker\StoreReviewRequest;
use App\Http\Resources\Seeker\ReviewResource;
use App\Models\Review;

class ReviewController extends Controller
{
    public function store(StoreReviewRequest $request)
    {
        $review = Review::create([
            'user_id' => $request->input('user_id'),
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
}
