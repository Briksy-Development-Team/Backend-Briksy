<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\SuperAdmin\PropertyIndexRequest;
use App\Http\Resources\SuperAdmin\PropertyResource;
use App\Models\PropertyListing;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;

class PropertyController extends Controller
{
    public function index(PropertyIndexRequest $request): JsonResponse
    {
        $query = PropertyListing::query()
            ->with([
                'organization:id,name,slug',
                'organization.soleTraderProfiles:id,organization_id',
                'propertyType:id,name,slug',
                'features:id,name,slug',
            ]);

        ApiQueryBuilder::applySearch($query, $request->search(), $request->searchableColumns());
        ApiQueryBuilder::applyFilters($query, $request->filters(), $request->allowedFilters());
        ApiQueryBuilder::applySort($query, $request->sort(), $request->direction(), $request->allowedSorts(), 'created_at');

        $paginator = $query->paginate($request->perPage())->withQueryString();

        return $this->paginated(
            PropertyResource::collection($paginator),
            $paginator,
            'Properties retrieved successfully.'
        );
    }

    public function show(PropertyListing $property): JsonResponse
    {
        $property->load([
            'organization:id,name,slug',
            'organization.soleTraderProfiles:id,organization_id',
            'propertyType:id,name,slug',
            'features:id,name,slug',
        ]);

        return $this->success(
            new PropertyResource($property),
            'Property retrieved successfully.'
        );
    }
}
