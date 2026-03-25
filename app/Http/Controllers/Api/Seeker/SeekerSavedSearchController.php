<?php

namespace App\Http\Controllers\Api\Seeker;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Seeker\StoreSeekerSavedSearchRequest;
use App\Http\Requests\Api\Seeker\UpdateSeekerSavedSearchRequest;
use App\Http\Resources\Seeker\SeekerSavedSearchResource;
use App\Models\SeekerSavedSearch;
use Illuminate\Http\JsonResponse;

class SeekerSavedSearchController extends Controller
{
    public function index(): JsonResponse
    {
        $searches = SeekerSavedSearch::query()
            ->where('user_id', request()->user()->id)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return $this->paginated(
            SeekerSavedSearchResource::collection($searches),
            $searches,
            'Saved searches retrieved successfully.'
        );
    }

    public function store(StoreSeekerSavedSearchRequest $request): JsonResponse
    {
        $payload = $request->validated();

        if (($payload['is_default'] ?? false) === true) {
            SeekerSavedSearch::query()
                ->where('user_id', $request->user()->id)
                ->update(['is_default' => false]);
        }

        $savedSearch = SeekerSavedSearch::query()->create([
            ...$payload,
            'user_id' => $request->user()->id,
            'is_default' => (bool) ($payload['is_default'] ?? false),
        ]);

        return $this->created(
            new SeekerSavedSearchResource($savedSearch),
            'Saved search created successfully.'
        );
    }

    public function show(SeekerSavedSearch $savedSearch): JsonResponse
    {
        if ($savedSearch->user_id !== request()->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view this saved search.',
            ], 403);
        }

        return $this->success(
            new SeekerSavedSearchResource($savedSearch),
            'Saved search retrieved successfully.'
        );
    }

    public function update(UpdateSeekerSavedSearchRequest $request, SeekerSavedSearch $savedSearch): JsonResponse
    {
        if ($savedSearch->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to update this saved search.',
            ], 403);
        }

        $payload = $request->validated();

        if (($payload['is_default'] ?? false) === true) {
            SeekerSavedSearch::query()
                ->where('user_id', $request->user()->id)
                ->where('id', '!=', $savedSearch->id)
                ->update(['is_default' => false]);
        }

        $savedSearch->fill($payload);
        $savedSearch->save();

        return $this->success(
            new SeekerSavedSearchResource($savedSearch->fresh()),
            'Saved search updated successfully.'
        );
    }

    public function destroy(SeekerSavedSearch $savedSearch): JsonResponse
    {
        if ($savedSearch->user_id !== request()->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to delete this saved search.',
            ], 403);
        }

        $savedSearch->delete();

        return $this->success(null, 'Saved search deleted successfully.');
    }
}
