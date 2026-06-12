<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\SuperAdmin\OrganizationIndexRequest;
use App\Http\Requests\Api\SuperAdmin\OrganizationStoreRequest;
use App\Http\Requests\Api\SuperAdmin\OrganizationUpdateRequest;
use App\Http\Resources\SuperAdmin\OrganizationResource;
use App\Models\Organization;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index(OrganizationIndexRequest $request): JsonResponse
    {
        $query = Organization::query()->with(['organizationType', 'plan']);

        ApiQueryBuilder::applySearch($query, $request->search(), $request->searchableColumns());
        ApiQueryBuilder::applyFilters($query, $request->filters(), $request->allowedFilters());

        if ($request->filled('filter.type_slug')) {
            $typeSlug = $request->string('filter.type_slug')->toString();
            $query->whereHas('organizationType', fn ($typeQuery) => $typeQuery->where('slug', $typeSlug));
        }

        ApiQueryBuilder::applySort($query, $request->sort(), $request->direction(), $request->allowedSorts(), 'created_at');

        $paginator = $query->paginate($request->perPage())->withQueryString();

        return $this->paginated(
            OrganizationResource::collection($paginator),
            $paginator,
            'Organizations retrieved successfully.'
        );
    }

    public function show(Organization $organization): JsonResponse
    {
        $organization->load(['organizationType', 'plan']);

        return $this->success(
            new OrganizationResource($organization),
            'Organization retrieved successfully.'
        );
    }

    public function store(OrganizationStoreRequest $request): JsonResponse
    {
        $organization = Organization::query()->create($request->validated());
        $organization->load(['organizationType', 'plan']);

        return $this->created(
            new OrganizationResource($organization),
            'Organization created successfully.'
        );
    }

    public function update(OrganizationUpdateRequest $request, Organization $organization): JsonResponse
    {
        $organization->fill($request->validated());
        $organization->save();
        $organization->load(['organizationType', 'plan']);

        return $this->success(
            new OrganizationResource($organization),
            'Organization updated successfully.'
        );
    }

    public function destroy(Organization $organization): JsonResponse
    {
        $organization->delete();

        return $this->success([], 'Organization deactivated successfully.');
    }

    public function restore(string $organization): JsonResponse
    {
        $organizationModel = Organization::withTrashed()->findOrFail($organization);
        $organizationModel->restore();

        return $this->success(
            new OrganizationResource($organizationModel->fresh()->load(['organizationType', 'plan'])),
            'Organization activated successfully.'
        );
    }

    public function assignPlan(Request $request, Organization $organization): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => ['nullable', 'uuid', 'exists:subscription_plans,id'],
        ]);

        $organization->update([
            'plan_id' => $validated['plan_id'] ?? null,
        ]);

        $organization->load(['organizationType', 'plan']);

        return $this->success(
            new OrganizationResource($organization),
            'Plan assigned successfully.'
        );
    }
}
