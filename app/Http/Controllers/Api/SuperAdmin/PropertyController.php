<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\SuperAdmin\PropertyListingIndexRequest;
use App\Http\Resources\Admin\AdminPropertyListingResource;
use App\Models\PropertyListing;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;

class PropertyController extends Controller
{
    public function index(PropertyListingIndexRequest $request): JsonResponse
    {
        $query = PropertyListing::query()->with(['organization.organizationType', 'creator']);

        ApiQueryBuilder::applySearch($query, $request->search(), $request->searchableColumns());

        if ($request->filled('filter.status')) {
            $query->where('status', $request->string('filter.status')->toString());
        }

        if ($request->filled('filter.suburb')) {
            $query->where('suburb', $request->string('filter.suburb')->toString());
        }

        if ($request->filled('filter.postcode')) {
            $query->where('postcode', $request->string('filter.postcode')->toString());
        }

        if ($request->filled('filter.organization_slug')) {
            $query->whereHas('organization', function ($organizationQuery) use ($request): void {
                $organizationQuery->where('slug', $request->string('filter.organization_slug')->toString());
            });
        }

        if ($request->boolean('filter.verified_only')) {
            $query->whereHas('organization', fn ($organizationQuery) => $organizationQuery->where('is_verified', true));
        }

        ApiQueryBuilder::applySort($query, $request->sort(), $request->direction(), $request->allowedSorts(), 'created_at');

        $properties = $query->paginate($request->perPage())->withQueryString();

        return $this->paginated(
            AdminPropertyListingResource::collection($properties),
            $properties,
            'Property listings retrieved successfully.'
        );
    }

    public function show(PropertyListing $propertyListing): JsonResponse
    {
        $propertyListing->load(['organization.organizationType', 'creator', 'media']);

        return $this->success(
            new AdminPropertyListingResource($propertyListing),
            'Property listing retrieved successfully.'
        );
    }
}
