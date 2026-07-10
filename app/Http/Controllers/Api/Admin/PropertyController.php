<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Admin\PropertyListingIndexRequest;
use App\Http\Requests\Api\Admin\PropertyListingStoreRequest;
use App\Http\Requests\Api\Admin\PropertyListingUpdateRequest;
use App\Http\Resources\Admin\AdminPropertyListingResource;
use App\Models\ActivityLog;
use App\Models\Media;
use App\Models\PropertyListing;
use App\Services\DynamicIdGeneratorService;
use App\Services\NotificationService;
use App\Support\Properties\PropertyWorkflow;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
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

        $propertyListing->load([
            'organization.organizationType',
            'creator',
            'media',
            'propertyType',
            'reviewer',
            'locationVerifier',
            'activityLogs.user',
        ]);

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
            'generated_id' => $this->idGenerator->generate('properties'),
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
            'status' => PropertyWorkflow::STATUS_PENDING_REVIEW,
            'submitted_at' => now(),
            'location_verified' => false,
            'location_verified_by' => null,
            'location_verified_at' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
            'published_at' => null,
        ]);

        $this->storeListingMedia($listing, $request);
        $this->recordPropertyActivity(
            $request,
            $listing,
            PropertyWorkflow::ACTION_CREATED,
            sprintf('Property "%s" created.', $listing->title),
            null,
            $listing->toArray(),
            ['title' => 'Property created']
        );
        $this->recordPropertyActivity(
            $request,
            $listing,
            PropertyWorkflow::ACTION_SUBMITTED,
            sprintf('Property "%s" submitted for review.', $listing->title),
            null,
            ['status' => PropertyWorkflow::STATUS_PENDING_REVIEW],
            ['title' => 'Submitted for review']
        );

        $listing->load(['organization.organizationType', 'creator', 'media', 'propertyType', 'reviewer', 'locationVerifier', 'activityLogs.user']);

        $this->notificationService->notifySuperAdminTeam(
            $this->notificationService->buildPayload(
                PropertyWorkflow::ACTION_SUBMITTED,
                'New property submitted for review',
                sprintf(
                    'Property "%s" from %s was submitted by %s on %s.',
                    $listing->title,
                    $listing->organization?->name ?? 'an organisation',
                    $request->user()?->name ?? 'a user',
                    now()->toDateString()
                ),
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

        $before = $propertyListing->replicate()->toArray();
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

        $validated['status'] = PropertyWorkflow::STATUS_PENDING_REVIEW;
        $validated['submitted_at'] = now();
        $validated['reviewed_by'] = null;
        $validated['reviewed_at'] = null;
        $validated['rejection_reason'] = null;
        $validated['published_at'] = null;

        $propertyListing->fill($validated);
        $propertyListing->save();

        $this->storeListingMedia($propertyListing, $request);
        $this->recordPropertyActivity(
            $request,
            $propertyListing,
            PropertyWorkflow::ACTION_UPDATED,
            sprintf('Property "%s" updated and resubmitted for review.', $propertyListing->title),
            $before,
            $propertyListing->fresh()->toArray(),
            ['title' => 'Property updated']
        );
        $this->recordPropertyActivity(
            $request,
            $propertyListing,
            PropertyWorkflow::ACTION_SUBMITTED,
            sprintf('Property "%s" resubmitted for review.', $propertyListing->title),
            null,
            ['status' => PropertyWorkflow::STATUS_PENDING_REVIEW],
            ['title' => 'Resubmitted for review']
        );

        $propertyListing->load(['organization.organizationType', 'creator', 'media', 'propertyType', 'reviewer', 'locationVerifier', 'activityLogs.user']);

        $this->notificationService->notifySuperAdminTeam(
            $this->notificationService->buildPayload(
                PropertyWorkflow::ACTION_SUBMITTED,
                'Property resubmitted for review',
                sprintf('Property "%s" was resubmitted by %s.', $propertyListing->title, $request->user()?->name ?? 'a user'),
                PropertyListing::class,
                $propertyListing->id,
                "/super-admin/property-management?highlight={$propertyListing->id}",
                'normal',
                $request->user()?->id,
                $propertyListing->org_id
            ),
            'Property resubmitted for review',
            'Review property'
        );

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
                'file_url' => $this->storageUrl($request, $path),
                'media_type' => 'image',
                'is_primary' => $index === 0 && $mediaOrder === 0,
                'sort_order' => ++$mediaOrder,
            ]);
        }

        foreach ($uploadedVideos as $file) {
            $path = $file->storePublicly("property-listings/{$listing->id}/videos", 'public');

            Media::query()->create([
                'property_listing_id' => $listing->id,
                'file_url' => $this->storageUrl($request, $path),
                'media_type' => 'video',
                'is_primary' => false,
                'sort_order' => ++$mediaOrder,
            ]);
        }
    }

    private function storageUrl(Request $request, string $path): string
    {
        $baseUrl = rtrim($request->getSchemeAndHttpHost(), '/');

        return $baseUrl.'/storage/'.ltrim($path, '/');
    }

    private function recordPropertyActivity(
        Request $request,
        PropertyListing $listing,
        string $action,
        string $description,
        ?array $oldValues = null,
        ?array $newValues = null,
        array $metadata = [],
    ): void {
        if (!Schema::hasTable('activity_logs')) {
            return;
        }

        $user = $request->user();

        ActivityLog::query()->create([
            'causer_id' => $user?->id,
            'subject_id' => $listing->id,
            'organization_id' => $listing->org_id,
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'user_email' => $user?->email,
            'user_role' => $user?->roles?->pluck('name')->first(),
            'action' => $action,
            'module' => PropertyWorkflow::MODULE,
            'description' => $description,
            'method' => $request->method(),
            'route' => $request->path(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => $metadata,
        ]);
    }
}
