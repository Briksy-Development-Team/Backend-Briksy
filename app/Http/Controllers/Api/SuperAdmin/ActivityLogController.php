<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Concerns\AppliesActivityLogFilters;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\SuperAdmin\ActivityLogIndexRequest;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;

class ActivityLogController extends Controller
{
    use AppliesActivityLogFilters;

    public function index(ActivityLogIndexRequest $request): JsonResponse
    {
        $query = ActivityLog::query()->with('organization');

        $this->applyActivityLogFilters($query, $request, true);

        $paginator = $query->paginate($request->perPage())->withQueryString();

        return $this->paginated(
            ActivityLogResource::collection($paginator)->resolve(),
            $paginator,
            'Activity logs retrieved successfully.'
        );
    }

    public function show(string $id): JsonResponse
    {
        $log = ActivityLog::query()->with('organization')->findOrFail($id);

        return $this->success(
            new ActivityLogResource($log),
            'Activity log retrieved successfully.'
        );
    }
}
