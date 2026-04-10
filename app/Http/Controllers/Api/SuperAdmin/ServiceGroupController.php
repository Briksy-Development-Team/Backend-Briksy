<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\SuperAdmin\ServiceGroupIndexRequest;
use App\Http\Resources\SuperAdmin\ServiceGroupResource;
use App\Models\ServiceGroup;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;

class ServiceGroupController extends Controller
{
    public function index(ServiceGroupIndexRequest $request): JsonResponse
    {
        $query = ServiceGroup::query()
            ->with('organizationType:id,name,slug')
            ->withCount(['services', 'organizations']);

        ApiQueryBuilder::applySearch($query, $request->search(), $request->searchableColumns());
        ApiQueryBuilder::applyFilters($query, $request->filters(), $request->allowedFilters());
        ApiQueryBuilder::applySort($query, $request->sort(), $request->direction(), $request->allowedSorts(), 'created_at');

        $paginator = $query->paginate($request->perPage())->withQueryString();

        return $this->paginated(
            ServiceGroupResource::collection($paginator),
            $paginator,
            'Service groups retrieved successfully.'
        );
    }

    public function show(ServiceGroup $serviceGroup): JsonResponse
    {
        $serviceGroup->load('organizationType:id,name,slug')
            ->loadCount(['services', 'organizations']);

        return $this->success(
            new ServiceGroupResource($serviceGroup),
            'Service group retrieved successfully.'
        );
    }
}

