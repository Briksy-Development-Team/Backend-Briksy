<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\SuperAdmin\PropertyFeatureGroupResource;
use App\Models\PropertyFeatureGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyFeatureController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PropertyFeatureGroup::query()
            ->with(['features' => fn ($featureQuery) => $featureQuery->orderBy('sort_order')])
            ->orderBy('sort_order');

        if ($groupSlug = $request->string('group_slug')->toString()) {
            $query->where('slug', $groupSlug);
        }

        $groups = $query->get();

        return $this->success(
            PropertyFeatureGroupResource::collection($groups),
            'Property feature taxonomy retrieved successfully.'
        );
    }
}

