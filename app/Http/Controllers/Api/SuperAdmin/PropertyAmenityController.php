<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Models\PropertyFeature;
use App\Models\PropertyFeatureGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PropertyAmenityController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        return $this->success(PropertyFeatureGroup::query()->with('features')->orderBy('sort_order')->orderBy('name')->get()->map(fn ($group): array => [
            'id' => $group->id,
            'name' => $group->name,
            'slug' => $group->slug,
            'sort_order' => $group->sort_order,
            'is_active' => (bool) $group->is_active,
            'features' => $group->features->sortBy([['sort_order', 'asc'], ['name', 'asc']])->values()->map(fn ($feature): array => [
                'id' => $feature->id,
                'group_id' => $feature->group_id,
                'name' => $feature->name,
                'slug' => $feature->slug,
                'sort_order' => $feature->sort_order,
                'is_active' => (bool) $feature->is_active,
            ])->all(),
        ])->values()->all(), 'Property amenities retrieved successfully.');
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'group_id' => ['required', 'uuid', 'exists:property_feature_groups,id'],
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', 'unique:property_features,slug'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $data['slug'] = Str::slug($data['slug'] ?? $data['name']);
        return $this->created($this->feature($feature = PropertyFeature::create($data)), 'Property amenity created successfully.');
    }

    public function update(Request $request, PropertyFeature $propertyFeature): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'group_id' => ['sometimes', 'uuid', 'exists:property_feature_groups,id'],
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', 'unique:property_features,slug,'.$propertyFeature->id],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if (array_key_exists('slug', $data)) $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
        $propertyFeature->update($data);
        return $this->success($this->feature($propertyFeature->fresh()), 'Property amenity updated successfully.');
    }

    public function destroy(PropertyFeature $propertyFeature): \Illuminate\Http\JsonResponse
    {
        if ($propertyFeature->propertyListings()->exists()) {
            $propertyFeature->update(['is_active' => false]);
            return $this->success($this->feature($propertyFeature->fresh()), 'Property amenity was deactivated because it is in use.');
        }
        $propertyFeature->delete();
        return $this->success([], 'Property amenity deleted successfully.');
    }

    private function feature(PropertyFeature $feature): array
    {
        return [
            'id' => $feature->id,
            'group_id' => $feature->group_id,
            'name' => $feature->name,
            'slug' => $feature->slug,
            'sort_order' => $feature->sort_order,
            'is_active' => (bool) $feature->is_active,
        ];
    }
}
