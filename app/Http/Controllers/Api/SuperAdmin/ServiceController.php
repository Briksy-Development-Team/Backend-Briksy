<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\SuperAdmin\ServiceIndexRequest;
use App\Http\Resources\SuperAdmin\ServiceResource;
use App\Models\Service;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;

class ServiceController extends Controller
{
    public function index(ServiceIndexRequest $request): JsonResponse
    {
        $query = Service::query()
            ->with([
                'organizationType:id,name,slug',
                'serviceGroup:id,name,slug',
            ])
            ->withCount('organizations');

        ApiQueryBuilder::applySearch($query, $request->search(), $request->searchableColumns());
        ApiQueryBuilder::applyFilters($query, $request->filters(), $request->allowedFilters());
        ApiQueryBuilder::applySort($query, $request->sort(), $request->direction(), $request->allowedSorts(), 'created_at');

        $paginator = $query->paginate($request->perPage())->withQueryString();

        return $this->paginated(
            ServiceResource::collection($paginator),
            $paginator,
            'Services retrieved successfully.'
        );
    }

    public function show(Service $service): JsonResponse
    {
        $service->load([
            'organizationType:id,name,slug',
            'serviceGroup:id,name,slug',
        ])->loadCount('organizations');

        return $this->success(
            new ServiceResource($service),
            'Service retrieved successfully.'
        );
    }
}

