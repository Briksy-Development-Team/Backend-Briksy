<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\SuperAdmin\OrganizationTypeIndexRequest;
use App\Http\Requests\Api\SuperAdmin\OrganizationTypeStoreRequest;
use App\Http\Requests\Api\SuperAdmin\OrganizationTypeUpdateRequest;
use App\Http\Resources\SuperAdmin\OrganizationTypeResource;
use App\Models\OrganizationType;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;

class OrganizationTypeController extends Controller
{
    public function index(OrganizationTypeIndexRequest $request): JsonResponse
    {
        $query = OrganizationType::query();

        ApiQueryBuilder::applySearch($query, $request->search(), $request->searchableColumns());
        ApiQueryBuilder::applySort($query, $request->sort(), $request->direction(), $request->allowedSorts(), 'created_at');

        $organizationTypes = $query->paginate($request->perPage())->withQueryString();

        return $this->paginated(
            OrganizationTypeResource::collection($organizationTypes),
            $organizationTypes,
            'Organization types retrieved successfully.'
        );
    }

    public function store(OrganizationTypeStoreRequest $request): JsonResponse
    {
        $organizationType = OrganizationType::query()->create($request->validated());

        return $this->created(
            new OrganizationTypeResource($organizationType),
            'Organization type created successfully.'
        );
    }

    public function update(OrganizationTypeUpdateRequest $request, OrganizationType $organizationType): JsonResponse
    {
        $organizationType->fill($request->validated());
        $organizationType->save();

        return $this->success(
            new OrganizationTypeResource($organizationType),
            'Organization type updated successfully.'
        );
    }
}