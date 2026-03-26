<?php

namespace App\Http\Controllers\Api\Seeker;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Seeker\UpdateSeekerProfileRequest;
use App\Http\Resources\Seeker\SeekerProfileResource;
use App\Models\SeekerProfile;
use Illuminate\Http\JsonResponse;

class SeekerProfileController extends Controller
{
    public function show(): JsonResponse
    {
        $profile = SeekerProfile::query()->firstOrCreate(
            ['user_id' => request()->user()->id],
            [
                'current_postcode' => null,
                'preferred_budget_min' => null,
                'preferred_budget_max' => null,
            ]
        );

        return $this->success(
            new SeekerProfileResource($profile),
            'Seeker profile retrieved successfully.'
        );
    }

    public function update(UpdateSeekerProfileRequest $request): JsonResponse
    {
        $profile = SeekerProfile::query()->firstOrCreate(['user_id' => $request->user()->id]);

        $profile->fill($request->validated());
        $profile->save();

        return $this->success(
            new SeekerProfileResource($profile->fresh()),
            'Seeker profile updated successfully.'
        );
    }
}
