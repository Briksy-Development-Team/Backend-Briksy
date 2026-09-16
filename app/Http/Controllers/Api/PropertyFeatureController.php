<?php

namespace App\Http\Controllers\Api;

use App\Models\PropertyFeatureGroup;
use Illuminate\Http\JsonResponse;

class PropertyFeatureController extends Controller
{
    public function index(): JsonResponse
    {
        $groups = PropertyFeatureGroup::query()
            ->with(['features' => fn ($query) => $query->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (PropertyFeatureGroup $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'slug' => $group->slug,
                'features' => $group->features->map(fn ($feature): array => [
                    'id' => $feature->id,
                    'name' => $feature->name,
                    'slug' => $feature->slug,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        return $this->success($groups, 'Property features retrieved successfully.');
    }
}
