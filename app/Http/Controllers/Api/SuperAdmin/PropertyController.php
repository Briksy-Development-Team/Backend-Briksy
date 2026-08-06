<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\SuperAdmin\PropertyListingIndexRequest;
use App\Http\Resources\Admin\AdminPropertyListingResource;
use App\Models\ActivityLog;
use App\Models\PropertyListing;
use App\Services\NotificationService;
use App\Support\Properties\PropertyWorkflow;
use Illuminate\Database\Eloquent\Builder;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PropertyController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

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
        $propertyListing->load([
            'organization.organizationType',
            'creator',
            'media',
            'propertyType',
            'reviewer',
            'locationVerifier',
            'offers.creator',
            'activityLogs.user',
        ]);

        return $this->success(
            new AdminPropertyListingResource($propertyListing),
            'Property listing retrieved successfully.'
        );
    }

    public function approve(Request $request, PropertyListing $propertyListing): JsonResponse
    {
        return $this->transition(
            $request,
            $propertyListing,
            PropertyWorkflow::STATUS_APPROVED,
            PropertyWorkflow::ACTION_APPROVED,
            'Property approved',
            sprintf('Property "%s" was approved.', $propertyListing->title),
            'Property approved',
            'Review property'
        );
    }

    public function reject(Request $request, PropertyListing $propertyListing): JsonResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ]);

        return $this->transition(
            $request,
            $propertyListing,
            PropertyWorkflow::STATUS_REJECTED,
            PropertyWorkflow::ACTION_REJECTED,
            'Property rejected',
            sprintf('Property "%s" was rejected.', $propertyListing->title),
            'Property rejected',
            'Review property',
            ['rejection_reason' => $validated['rejection_reason']],
        );
    }

    public function publish(Request $request, PropertyListing $propertyListing): JsonResponse
    {
        abort_unless($propertyListing->location_verified, 422, 'Property must be location verified before publishing.');

        return $this->transition(
            $request,
            $propertyListing,
            PropertyWorkflow::STATUS_PUBLISHED,
            PropertyWorkflow::ACTION_PUBLISHED,
            'Property published',
            sprintf('Property "%s" was published.', $propertyListing->title),
            'Property published',
            'Review property'
        );
    }

    public function archive(Request $request, PropertyListing $propertyListing): JsonResponse
    {
        return $this->transition(
            $request,
            $propertyListing,
            PropertyWorkflow::STATUS_ARCHIVED,
            PropertyWorkflow::ACTION_ARCHIVED,
            'Property archived',
            sprintf('Property "%s" was archived.', $propertyListing->title),
            'Property archived',
            'Review property'
        );
    }

    public function verifyLocation(Request $request, PropertyListing $propertyListing): JsonResponse
    {
        return $this->transitionLocationVerification(
            $request,
            $propertyListing,
            true,
            PropertyWorkflow::ACTION_LOCATION_VERIFIED,
            'Location verified',
            sprintf('Property "%s" location was verified.', $propertyListing->title),
            'Location verified',
            'Review property',
        );
    }

    public function unverifyLocation(Request $request, PropertyListing $propertyListing): JsonResponse
    {
        return $this->transitionLocationVerification(
            $request,
            $propertyListing,
            false,
            PropertyWorkflow::ACTION_LOCATION_UNVERIFIED,
            'Location verification removed',
            sprintf('Property "%s" location verification was removed.', $propertyListing->title),
            'Location verification removed',
            'Review property',
        );
    }

    private function baseQuery(): Builder
    {
        return PropertyListing::query()->with(['organization.organizationType', 'creator', 'propertyType', 'offers.creator']);
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

        if ($request->filled('filter.organization_slug')) {
            $query->whereHas('organization', function ($organizationQuery) use ($request): void {
                $organizationQuery->where('slug', $request->string('filter.organization_slug')->toString());
            });
        }

        if ($request->boolean('filter.verified_only')) {
            $query->whereHas('organization', fn ($organizationQuery) => $organizationQuery->where('is_verified', true));
        }
    }

    private function transition(
        Request $request,
        PropertyListing $propertyListing,
        string $status,
        string $action,
        string $title,
        string $description,
        string $mailSubject,
        string $mailCtaLabel,
        array $extraUpdates = [],
    ): JsonResponse {
        $before = $propertyListing->replicate()->toArray();

        $updates = array_merge([
            'status' => $status,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
            'rejection_reason' => $extraUpdates['rejection_reason'] ?? ($status === PropertyWorkflow::STATUS_REJECTED ? null : $propertyListing->rejection_reason),
        ], $extraUpdates);

        if ($status === PropertyWorkflow::STATUS_PUBLISHED) {
            $updates['published_at'] = now();
        }

        if ($status !== PropertyWorkflow::STATUS_REJECTED) {
            $updates['rejection_reason'] = null;
        }

        $propertyListing->fill($updates);
        $propertyListing->save();

        $this->recordActivity($request, $propertyListing, $action, $description, $before, $propertyListing->fresh()->toArray(), ['title' => $title]);

        if ($status === PropertyWorkflow::STATUS_REJECTED) {
            $this->notificationService->notifyAdminsForOrganisation(
                $propertyListing->org_id,
                $this->notificationService->buildPayload(
                    $action,
                    $title,
                    sprintf('%s Reason: %s', $description, $updates['rejection_reason'] ?? 'No reason provided.'),
                    PropertyListing::class,
                    $propertyListing->id,
                    "/admin/property-management?highlight={$propertyListing->id}",
                    'high',
                    $request->user()?->id,
                    $propertyListing->org_id
                ),
                $mailSubject,
                $mailCtaLabel
            );
        } else {
            $this->notificationService->notifyAdminsForOrganisation(
                $propertyListing->org_id,
                $this->notificationService->buildPayload(
                    $action,
                    $title,
                    $description,
                    PropertyListing::class,
                    $propertyListing->id,
                    "/admin/property-management?highlight={$propertyListing->id}",
                    'normal',
                    $request->user()?->id,
                    $propertyListing->org_id
                ),
                $mailSubject,
                $mailCtaLabel
            );
        }

        $propertyListing->load(['organization.organizationType', 'creator', 'media', 'propertyType', 'reviewer', 'locationVerifier', 'activityLogs.user']);

        return $this->success(new AdminPropertyListingResource($propertyListing), $title . ' successfully.');
    }

    private function transitionLocationVerification(
        Request $request,
        PropertyListing $propertyListing,
        bool $verified,
        string $action,
        string $title,
        string $description,
        string $mailSubject,
        string $mailCtaLabel,
    ): JsonResponse {
        $before = $propertyListing->replicate()->toArray();

        $propertyListing->fill([
            'location_verified' => $verified,
            'location_verified_by' => $verified ? $request->user()?->id : null,
            'location_verified_at' => $verified ? now() : null,
        ]);
        $propertyListing->save();

        $this->recordActivity($request, $propertyListing, $action, $description, $before, $propertyListing->fresh()->toArray(), ['title' => $title]);

        $payload = $this->notificationService->buildPayload(
            $action,
            $title,
            $description,
            PropertyListing::class,
            $propertyListing->id,
            "/admin/property-management?highlight={$propertyListing->id}",
            'normal',
            $request->user()?->id,
            $propertyListing->org_id
        );

        if ($verified) {
            $this->notificationService->notifyAdminsForOrganisation($propertyListing->org_id, $payload, $mailSubject, $mailCtaLabel);
        } else {
            $this->notificationService->notifyAdminsForOrganisation($propertyListing->org_id, $payload, $mailSubject, $mailCtaLabel);
        }

        $propertyListing->load(['organization.organizationType', 'creator', 'media', 'propertyType', 'reviewer', 'locationVerifier', 'activityLogs.user']);

        return $this->success(new AdminPropertyListingResource($propertyListing), $title . ' successfully.');
    }

    private function recordActivity(
        Request $request,
        PropertyListing $propertyListing,
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
            'subject_id' => $propertyListing->id,
            'organization_id' => $propertyListing->org_id,
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
