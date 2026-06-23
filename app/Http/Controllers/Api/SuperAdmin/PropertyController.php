<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\SuperAdmin\PropertyListingIndexRequest;
use App\Http\Resources\Admin\AdminPropertyListingResource;
use App\Models\PropertyListing;
use Illuminate\Database\Eloquent\Builder;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;

class PropertyController extends Controller
{
    public function index(PropertyListingIndexRequest $request): JsonResponse
    {
        $query = $this->baseQuery();
        ApiQueryBuilder::applySearch($query, $request->search(), $request->searchableColumns());
        $this->applyFilters($query, $request);

        ApiQueryBuilder::applySort($query, $request->sort(), $request->direction(), $request->allowedSorts(), 'created_at');

        $properties = $query->paginate($request->perPage())->withQueryString();

        return $this->paginated(
            AdminPropertyListingResource::collection($properties),
            $properties,
            'Property listings retrieved successfully.'
        );
    }

    public function map(PropertyListingIndexRequest $request): JsonResponse
    {
        $query = $this->baseQuery();
        ApiQueryBuilder::applySearch($query, $request->search(), $request->searchableColumns());
        $this->applyFilters($query, $request);

        $properties = $query
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        return $this->success(
            AdminPropertyListingResource::collection($properties)->resolve(),
            'Property map data retrieved successfully.'
        );
    }

    public function show(PropertyListing $propertyListing): JsonResponse
    {
        $propertyListing->load(['organization.organizationType', 'creator', 'media', 'propertyType']);

        return $this->success(
            new AdminPropertyListingResource($propertyListing),
            'Property listing retrieved successfully.'
        );
    }

    private function baseQuery(): Builder
    {
        return PropertyListing::query()->with(['organization.organizationType', 'creator', 'propertyType']);
    }

    private function applyFilters(Builder $query, PropertyListingIndexRequest $request): void
    {
        if ($request->filled('filter.status')) {
            $query->where('status', $request->string('filter.status')->toString());
        }

        if ($request->filled('filter.suburb')) {
            $query->where('suburb', $request->string('filter.suburb')->toString());
        }

        if ($request->filled('filter.state')) {
            $query->where('state', $request->string('filter.state')->toString());
        }

        if ($request->filled('filter.postcode')) {
            $query->where('postcode', $request->string('filter.postcode')->toString());
        }

        if ($request->filled('filter.property_type_id')) {
            $query->where('property_type_id', $request->string('filter.property_type_id')->toString());
        }

        if ($request->filled('filter.organization_slug')) {
            $query->whereHas('organization', function ($organizationQuery) use ($request): void {
                $organizationQuery->where('slug', $request->string('filter.organization_slug')->toString());
            });
        }

        if ($request->boolean('filter.verified_only')) {
            $query->whereHas('organization', fn ($organizationQuery) => $organizationQuery->where('is_verified', true));
        }
    }
}
