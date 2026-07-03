<?php

namespace App\Http\Controllers\Api\Seeker;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Seeker\OrganizationIndexRequest;
use App\Http\Resources\Seeker\OrganizationResource;
use App\Models\Organization;
use App\Models\VisitorLog;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class OrganizationSearchController extends Controller
{
    public function index(OrganizationIndexRequest $request)
    {
        $query = Organization::query()
            ->with(['organizationType', 'services', 'serviceGroups']);

        ApiQueryBuilder::applySearch($query, $request->search(), ['name', 'slug', 'abn']);

        if ($request->filled('type')) {
            $query->whereHas('organizationType', fn ($typeQuery) => $typeQuery->where('slug', $request->string('type')->toString()));
        }

        if ($request->filled('service_slug')) {
            $query->whereHas('services', fn ($serviceQuery) => $serviceQuery->where('services.slug', $request->string('service_slug')->toString()));
        }

        if ($request->filled('service_group_slug')) {
            $query->whereHas('serviceGroups', fn ($groupQuery) => $groupQuery->where('service_groups.slug', $request->string('service_group_slug')->toString()));
        }

        if ($request->boolean('verified_only')) {
            $query->where('is_verified', true);
        }

        if ($request->filled('sort')) {
            ApiQueryBuilder::applySort($query, $request->sort(), $request->direction(), $request->allowedSorts(), 'priority');
        } else {
            $query->orderBy('ranking_priority')->orderByDesc('avg_org_rating');
        }

        $organizations = $query->paginate($request->perPage())->withQueryString();

        return $this->paginated(
            OrganizationResource::collection($organizations),
            $organizations,
            'Organizations retrieved successfully.'
        );
    }

    public function show(Organization $organization)
    {
        $organization->load(['organizationType', 'services', 'serviceGroups']);
        $this->recordVisit($organization, request());

        return $this->success(
            new OrganizationResource($organization),
            'Organization retrieved successfully.'
        );
    }

    private function recordVisit(Organization $organization, Request $request): void
    {
        if (!Schema::hasTable('visitor_logs')) {
            return;
        }

        VisitorLog::query()->create([
            'viewer_id' => $request->user()?->id,
            'organization_id' => $organization->id,
            'property_listing_id' => null,
            'ip_address' => $request->ip(),
        ]);
    }
}
