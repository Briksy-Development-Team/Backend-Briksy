<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Admin\ActivityLogIndexRequest;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(ActivityLogIndexRequest $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $query = ActivityLog::query()
            ->with('organization')
            ->where('organization_id', $organizationId)
            ->whereIn('user_role', ['admin', 'admin_staff']);

        $this->applyFilters($query, $request->filters());
        ApiQueryBuilder::applySearch($query, $request->search(), $request->searchableColumns());
        ApiQueryBuilder::applyFilters($query, $request->filters(), $request->allowedFilters());
        ApiQueryBuilder::applySort($query, $request->sort(), $request->direction(), $request->allowedSorts(), 'created_at');

        $logs = $query->paginate($request->perPage())->withQueryString();

        return $this->paginated(
            ActivityLogResource::collection($logs)->resolve(),
            $logs,
            'Activity logs retrieved successfully.'
        );
    }

    public function show(Request $request, string $activityLog): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $log = ActivityLog::query()
            ->with('organization')
            ->where('organization_id', $organizationId)
            ->whereIn('user_role', ['admin', 'admin_staff'])
            ->findOrFail($activityLog);

        return $this->success(new ActivityLogResource($log), 'Activity log retrieved successfully.');
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['user'])) {
            $value = trim((string) $filters['user']);
            $query->where(function (Builder $builder) use ($value): void {
                $builder->where('user_name', 'like', "%{$value}%")
                    ->orWhere('user_email', 'like', "%{$value}%")
                    ->orWhere('user_id', 'like', "%{$value}%");
            });
        }

        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (!empty($filters['module'])) {
            $query->where('module', $filters['module']);
        }

        if (!empty($filters['ip_address'])) {
            $query->where('ip_address', 'like', '%' . trim((string) $filters['ip_address']) . '%');
        }

        if (!empty($filters['device'])) {
            $device = trim((string) $filters['device']);
            $query->where(function (Builder $builder) use ($device): void {
                $builder->where('user_agent', 'like', "%{$device}%")
                    ->orWhere('metadata->device', 'like', "%{$device}%");
            });
        }
    }

    private function organizationId(Request $request): string
    {
        $organizationId = $request->user()?->organization_id;
        abort_unless($organizationId, 403, 'Admin account is not assigned to an organization.');

        return $organizationId;
    }
}
