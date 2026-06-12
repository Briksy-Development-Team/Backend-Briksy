<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Admin\PropertyListingIndexRequest;
use App\Http\Requests\Api\Admin\PropertyListingStoreRequest;
use App\Http\Requests\Api\Admin\PropertyListingUpdateRequest;
use App\Http\Resources\Admin\AdminPropertyListingResource;
use App\Models\PropertyListing;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function index(PropertyListingIndexRequest $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'Admin account is not assigned to an organization.',
            ], 403);
        }

        $query = PropertyListing::query()
            ->where('org_id', $organizationId)
            ->with(['organization.organizationType', 'creator']);

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

    public function show(Request $request, PropertyListing $propertyListing): JsonResponse
    {
        abort_unless($this->isInAdminOrganization($request, $propertyListing), 403);

        $propertyListing->load(['organization.organizationType', 'creator', 'media']);

        return $this->success(
            new AdminPropertyListingResource($propertyListing),
            'Property listing retrieved successfully.'
        );
    }

    public function store(PropertyListingStoreRequest $request): JsonResponse
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'message' => 'Admin account is not assigned to an organization.',
            ], 403);
        }

        $listing = PropertyListing::query()->create([
            'org_id' => $organizationId,
            'creator_id' => $request->user()->id,
            'avg_prop_rating' => 0,
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'status' => $request->input('status'),
            'suburb' => $request->input('suburb'),
            'postcode' => $request->input('postcode'),
        ]);
        $listing->load(['organization.organizationType', 'creator']);

        return $this->created(
            new AdminPropertyListingResource($listing),
            'Property listing created successfully.'
        );
    }

    public function update(PropertyListingUpdateRequest $request, PropertyListing $propertyListing): JsonResponse
    {
        abort_unless($this->isInAdminOrganization($request, $propertyListing), 403);

        $propertyListing->fill($request->validated());
        $propertyListing->save();
        $propertyListing->load(['organization.organizationType', 'creator']);

        return $this->success(
            new AdminPropertyListingResource($propertyListing),
            'Property listing updated successfully.'
        );
    }

    public function destroy(Request $request, PropertyListing $propertyListing): JsonResponse
    {
        abort_unless($this->isInAdminOrganization($request, $propertyListing), 403);

        $propertyListing->delete();

        return $this->success([], 'Property listing deleted successfully.');
    }

    private function isInAdminOrganization(Request $request, PropertyListing $propertyListing): bool
    {
        $organizationId = $request->user()?->organization_id;

        return (bool) $organizationId && $propertyListing->org_id === $organizationId;
    }
}
