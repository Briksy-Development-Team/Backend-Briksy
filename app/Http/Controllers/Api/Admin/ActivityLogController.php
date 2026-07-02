<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\AppliesActivityLogFilters;
use App\Http\Controllers\Api\Concerns\AppliesOrganizationScope;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Admin\ActivityLogIndexRequest;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    use AppliesActivityLogFilters;
    use AppliesOrganizationScope;

    public function index(ActivityLogIndexRequest $request): JsonResponse
    {
        $organizationId = $this->requireOrganizationId($request);

        $query = ActivityLog::query()
            ->with('organization')
            ->where('organization_id', $organizationId);

        $this->applyActivityLogFilters($query, $request, false);

        $paginator = $query->paginate($request->perPage())->withQueryString();

        return $this->paginated(
            ActivityLogResource::collection($paginator)->resolve(),
            $paginator,
            'Activity logs retrieved successfully.'
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $organizationId = $this->requireOrganizationId($request);

        $log = ActivityLog::query()->with('organization')->findOrFail($id);

        abort_unless($log->organization_id === $organizationId, 403, 'You are not authorized to access this resource.');

        return $this->success(
            new ActivityLogResource($log),
            'Activity log retrieved successfully.'
        );
    }
}
