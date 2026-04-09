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

class OrganizationController extends Controller
{
    public function index(OrganizationIndexRequest $request): JsonResponse
    {
        $query = Organization::query()->with('organizationType');

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
        $organization->load('organizationType');

        return $this->success(
            new OrganizationResource($organization),
            'Organization retrieved successfully.'
        );
    }

    public function store(OrganizationStoreRequest $request): JsonResponse
    {
        $organization = Organization::query()->create($request->validated());
        $organization->load('organizationType');

        return $this->created(
            new OrganizationResource($organization),
            'Organization created successfully.'
        );
    }

    public function update(OrganizationUpdateRequest $request, Organization $organization): JsonResponse
    {
        $organization->fill($request->validated());
        $organization->save();
        $organization->load('organizationType');

        return $this->success(
            new OrganizationResource($organization),
            'Organization updated successfully.'
        );
    }
}
