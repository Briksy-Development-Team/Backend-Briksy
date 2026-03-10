<?php

namespace App\Http\Controllers\Api\Seeker;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Seeker\PropertyListingIndexRequest;
use App\Http\Resources\Seeker\PropertyListingResource;
use App\Models\PropertyListing;
use App\Support\Query\ApiQueryBuilder;

class PropertySearchController extends Controller
{
    public function index(PropertyListingIndexRequest $request)
    {
        $query = PropertyListing::query()
            ->published()
            ->with(['organization.organizationType', 'media' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order')]);

        ApiQueryBuilder::applySearch($query, $request->search(), ['title', 'description', 'suburb', 'postcode']);
        ApiQueryBuilder::applyExactFilters($query, [
            'suburb' => $request->input('suburb'),
            'postcode' => $request->input('postcode'),
        ]);

        if ($request->filled('organization_slug')) {
            $query->whereHas('organization', fn ($organizationQuery) => $organizationQuery->where('slug', $request->string('organization_slug')->toString()));
        }

        if ($request->filled('organization_type')) {
            $query->whereHas('organization.organizationType', fn ($typeQuery) => $typeQuery->where('slug', $request->string('organization_type')->toString()));
        }

        if ($request->filled('service_slug')) {
            $query->whereHas('organization.services', fn ($serviceQuery) => $serviceQuery->where('services.slug', $request->string('service_slug')->toString()));
        }

        if ($request->boolean('verified_only')) {
            $query->whereHas('organization', fn ($organizationQuery) => $organizationQuery->where('is_verified', true));
        }

        ApiQueryBuilder::applySort($query, $request->sort(), $request->direction(), $request->allowedSorts(), 'created_at');

        $properties = $query->paginate($request->perPage())->withQueryString();

        return $this->paginated(
            PropertyListingResource::collection($properties),
            $properties,
            'Property listings retrieved successfully.'
        );
    }

    public function show(PropertyListing $propertyListing)
    {
        $property = PropertyListing::query()
            ->published()
            ->with(['organization.organizationType', 'media' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order')])
            ->findOrFail($propertyListing->id);

        return $this->success(
            new PropertyListingResource($property),
            'Property listing retrieved successfully.'
        );
    }
}
