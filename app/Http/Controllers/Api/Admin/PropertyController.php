<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Admin\PropertyListingIndexRequest;
use App\Http\Requests\Api\Admin\PropertyListingStoreRequest;
use App\Http\Requests\Api\Admin\PropertyListingUpdateRequest;
use App\Http\Resources\Admin\AdminPropertyListingResource;
use App\Models\Media;
use App\Models\PropertyListing;
use App\Services\DynamicIdGeneratorService;
use App\Services\NotificationService;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PropertyController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly DynamicIdGeneratorService $idGenerator
    )
    {
    }

    public function index(PropertyListingIndexRequest $request): JsonResponse
    {
        $query = $this->baseQuery($request);
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
        $query = $this->baseQuery($request);
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

    public function show(Request $request, PropertyListing $propertyListing): JsonResponse
    {
        abort_unless($this->isInAdminOrganization($request, $propertyListing), 403);

        $propertyListing->load(['organization.organizationType', 'creator', 'media', 'propertyType']);

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
            'generated_id' => $this->idGenerator->generate('properties', 'PROP') ?? 'PROP-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
            'property_type_id' => $request->input('property_type_id'),
            'avg_prop_rating' => 0,
            'address_line_1' => $request->input('address_line_1') ?? $request->input('address'),
            'address_line_2' => $request->input('address_line_2'),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'address' => $request->input('address'),
            'full_address' => $request->input('full_address') ?? $request->input('address'),
            'status' => $request->input('status'),
            'suburb' => $request->input('suburb'),
            'state' => $request->input('state'),
            'postcode' => $request->input('postcode'),
            'country' => $request->input('country') ?? 'Australia',
            'formatted_address' => $request->input('formatted_address') ?? $request->input('full_address'),
            'place_id' => $request->input('place_id'),
            'location_verified' => $request->boolean('location_verified'),
        ]);

        $this->storeListingMedia($listing, $request);

        $listing->load(['organization.organizationType', 'creator', 'media', 'propertyType']);

        $this->notificationService->notifySuperAdmins(
            $this->notificationService->buildPayload(
                'property_created',
                'New property added',
                sprintf('A new property "%s" was added by %s.', $listing->title, $listing->organization?->name ?? 'an organisation'),
                PropertyListing::class,
                $listing->id,
                "/super-admin/property-management?highlight={$listing->id}",
                'normal',
                $request->user()?->id,
                $organizationId
            ),
            'New property added',
            'Review property'
        );

        return $this->created(
            new AdminPropertyListingResource($listing),
            'Property listing created successfully.'
        );
    }

    public function update(PropertyListingUpdateRequest $request, PropertyListing $propertyListing): JsonResponse
    {
        abort_unless($this->isInAdminOrganization($request, $propertyListing), 403);

        $validated = $request->validated();
        if (array_key_exists('address', $validated) && !array_key_exists('full_address', $validated)) {
            $validated['full_address'] = $validated['address'];
        }
        if (array_key_exists('address_line_1', $validated) && !array_key_exists('address', $validated)) {
            $validated['address'] = $validated['address_line_1'];
        }
        if (array_key_exists('formatted_address', $validated) && !array_key_exists('full_address', $validated)) {
            $validated['full_address'] = $validated['formatted_address'];
        }
        if (array_key_exists('country', $validated) && blank($validated['country'])) {
            $validated['country'] = 'Australia';
        }

        $propertyListing->fill($validated);
        $propertyListing->save();

        $this->storeListingMedia($propertyListing, $request);

        $propertyListing->load(['organization.organizationType', 'creator', 'media', 'propertyType']);

        if (!$propertyListing->location_verified || $propertyListing->latitude === null || $propertyListing->longitude === null) {
            $this->notificationService->notifyAdminsForOrganisation(
                $propertyListing->org_id,
                $this->notificationService->buildPayload(
                    'property_location_missing',
                    'Property missing coordinates',
                    sprintf('Property "%s" needs verified map coordinates.', $propertyListing->title),
                    PropertyListing::class,
                    $propertyListing->id,
                    "/admin/property-management?highlight={$propertyListing->id}",
                    'high',
                    $request->user()?->id,
                    $propertyListing->org_id
                ),
                'Property location needs attention',
                'Review property'
            );
        }

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

    private function baseQuery(Request $request): Builder
    {
        $organizationId = $request->user()?->organization_id;

        if (!$organizationId) {
            abort(403, 'Admin account is not assigned to an organization.');
        }

        return PropertyListing::query()
            ->where('org_id', $organizationId)
            ->with(['organization.organizationType', 'creator', 'propertyType']);
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

        if ($request->filled('filter.organization_id')) {
            $query->where('org_id', $request->string('filter.organization_id')->toString());
        }

        if ($request->boolean('filter.verified_only')) {
            $query->whereHas('organization', fn ($organizationQuery) => $organizationQuery->where('is_verified', true));
        }
    }

    private function storeListingMedia(PropertyListing $listing, Request $request): void
    {
        $uploadedImages = $request->file('images', []);
        $uploadedVideos = $request->file('videos', []);

        $mediaOrder = (int) Media::query()
            ->where('property_listing_id', $listing->id)
            ->max('sort_order');

        foreach ($uploadedImages as $index => $file) {
            $path = $file->storePublicly("property-listings/{$listing->id}/images", 'public');

            Media::query()->create([
                'property_listing_id' => $listing->id,
                'file_url' => Storage::disk('public')->url($path),
                'media_type' => 'image',
                'is_primary' => $index === 0 && $mediaOrder === 0,
                'sort_order' => ++$mediaOrder,
            ]);
        }

        foreach ($uploadedVideos as $file) {
            $path = $file->storePublicly("property-listings/{$listing->id}/videos", 'public');

            Media::query()->create([
                'property_listing_id' => $listing->id,
                'file_url' => Storage::disk('public')->url($path),
                'media_type' => 'video',
                'is_primary' => false,
                'sort_order' => ++$mediaOrder,
            ]);
        }
    }
}
