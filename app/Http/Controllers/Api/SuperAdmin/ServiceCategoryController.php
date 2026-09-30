<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\SuperAdmin\ServiceCategoryResource;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ServiceCategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = ServiceCategory::query()->orderBy('sort_order')->orderBy('name');
        if ($request->boolean('active_only')) $query->where('is_active', true);
        return $this->success(ServiceCategoryResource::collection($query->get()), 'Service categories retrieved successfully.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:120', 'unique:service_categories,slug'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);
        $data['slug'] = Str::slug($data['slug'] ?? $data['name']);
        return $this->created(new ServiceCategoryResource(ServiceCategory::create($data)), 'Service category created successfully.');
    }

    public function update(Request $request, ServiceCategory $serviceCategory)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:120', 'unique:service_categories,slug,' . $serviceCategory->id],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);
        if (array_key_exists('slug', $data)) $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
        $serviceCategory->update($data);
        return $this->success(new ServiceCategoryResource($serviceCategory->fresh()), 'Service category updated successfully.');
    }

    public function destroy(ServiceCategory $serviceCategory)
    {
        $serviceCategory->delete();
        return $this->success([], 'Service category deleted successfully.');
    }
}
