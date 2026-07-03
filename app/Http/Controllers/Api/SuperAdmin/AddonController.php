<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\SuperAdmin\AddonIndexRequest;
use App\Http\Requests\Api\SuperAdmin\AddonStoreRequest;
use App\Http\Requests\Api\SuperAdmin\AddonUpdateRequest;
use App\Http\Resources\SuperAdmin\AddonResource;
use App\Models\Addon;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddonController extends Controller
{
    public function index(AddonIndexRequest $request): JsonResponse
    {
        $query = Addon::query();
        ApiQueryBuilder::applySearch($query, $request->search(), $request->searchableColumns());
        ApiQueryBuilder::applyFilters($query, $request->filters(), $request->allowedFilters());
        ApiQueryBuilder::applySort($query, $request->sort(), $request->direction(), $request->allowedSorts(), 'sort_order');

        $items = $query->paginate($request->perPage())->withQueryString();

        return $this->paginated(
            AddonResource::collection($items)->resolve(),
            $items,
            'Add-ons retrieved successfully.'
        );
    }

    public function store(AddonStoreRequest $request): JsonResponse
    {
        $addon = Addon::query()->create($request->validated());

        return $this->created(new AddonResource($addon), 'Add-on created successfully.');
    }

    public function show(Addon $addon): JsonResponse
    {
        return $this->success(new AddonResource($addon), 'Add-on retrieved successfully.');
    }

    public function update(AddonUpdateRequest $request, Addon $addon): JsonResponse
    {
        $addon->fill($request->validated());
        $addon->save();

        return $this->success(new AddonResource($addon), 'Add-on updated successfully.');
    }

    public function destroy(Addon $addon): JsonResponse
    {
        $addon->delete();

        return $this->success([], 'Add-on deleted successfully.');
    }

    public function toggle(Request $request, Addon $addon): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $addon->update(['is_active' => $validated['is_active']]);

        return $this->success(new AddonResource($addon->fresh()), 'Add-on status updated successfully.');
    }
}
