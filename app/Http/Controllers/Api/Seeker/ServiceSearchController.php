<?php

namespace App\Http\Controllers\Api\Seeker;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\Seeker\ServiceResource;
use App\Models\Service;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\Request;

class ServiceSearchController extends Controller
{
    public function index(Request $request)
    {
        $query = Service::query()
            ->where('is_active', true)
            ->whereHas('organization', fn ($organizationQuery) => $organizationQuery->where('is_verified', true))
            ->with(['organization.currentSubscription.plan', 'media']);

        ApiQueryBuilder::applySearch($query, $request->string('search')->toString(), ['name', 'title', 'category', 'description']);

        foreach (['category', 'organization_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        if ($request->filled('organization_slug')) {
            $query->whereHas('organization', fn ($organizationQuery) => $organizationQuery->where('slug', $request->string('organization_slug')->toString()));
        }

        $query->orderBy('name');
        $services = $query->paginate(min(max((int) $request->input('per_page', 24), 1), 100))->withQueryString();

        return $this->paginated(ServiceResource::collection($services), $services, 'Services retrieved successfully.');
    }

    public function show(Service $service)
    {
        abort_unless($service->is_active && $service->organization?->is_verified, 404);
        $service->load(['organization.currentSubscription.plan', 'media']);

        return $this->success(new ServiceResource($service), 'Service retrieved successfully.');
    }
}
