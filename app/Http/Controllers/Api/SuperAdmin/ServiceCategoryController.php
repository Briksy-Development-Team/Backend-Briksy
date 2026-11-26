<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\SuperAdmin\ServiceCategoryResource;
use App\Models\ServiceCategory;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ServiceCategoryController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', 'in:name,sort_order'],
            'direction' => ['nullable', 'in:asc,desc'],
            'filter.is_active' => ['nullable', 'boolean'],
        ]);

        $query = ServiceCategory::query();
        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }
        if ($request->filled('filter.is_active')) {
            $query->where('is_active', $request->boolean('filter.is_active'));
        }
        ApiQueryBuilder::applySearch($query, $request->input('search'), ['name', 'slug', 'description']);
        ApiQueryBuilder::applySort($query, $request->input('sort'), $request->input('direction', 'asc'), [
            'name' => 'name',
            'sort_order' => 'sort_order',
        ], 'sort_order');
        $query->orderBy('name')->orderBy('id');

        if ($request->has('per_page')) {
            $items = $query->paginate(ApiQueryBuilder::normalizePerPage($request->integer('per_page')))->withQueryString();

            return $this->paginated(ServiceCategoryResource::collection($items)->resolve(), $items, 'Service categories retrieved successfully.');
        }

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
