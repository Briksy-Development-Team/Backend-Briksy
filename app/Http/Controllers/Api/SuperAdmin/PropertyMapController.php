<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\SuperAdmin\PropertyMapIndexRequest;
use App\Http\Resources\SuperAdmin\PropertyMapResource;
use App\Services\PropertyMapService;
use Illuminate\Http\JsonResponse;

class PropertyMapController extends Controller
{
    public function __construct(private readonly PropertyMapService $propertyMapService)
    {
    }

    public function index(PropertyMapIndexRequest $request): JsonResponse
    {
        $properties = $this->propertyMapService->list($request);

        return $this->success(
            PropertyMapResource::collection($properties)->resolve(),
            'Property map data retrieved successfully.'
        );
    }
}
