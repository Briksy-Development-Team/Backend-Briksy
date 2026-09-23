<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\SuperAdmin\ServiceIndexRequest;
use App\Http\Requests\Api\SuperAdmin\ServiceStoreRequest;
use App\Http\Requests\Api\SuperAdmin\ServiceUpdateRequest;
use App\Http\Resources\SuperAdmin\ServiceMapResource;
use App\Http\Resources\SuperAdmin\ServiceResource;
use App\Models\Service;
use App\Models\ServiceMedia;
use App\Models\Organization;
use App\Models\ActivityLog;
use App\Services\DynamicIdGeneratorService;
use App\Services\NotificationService;
use App\Services\ServiceMapService;
use App\Support\Query\ApiQueryBuilder;
use App\Support\Business\PlanCapabilityResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class ServiceController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly DynamicIdGeneratorService $idGenerator,
        private readonly ServiceMapService $serviceMapService,
        private readonly PlanCapabilityResolver $planCapabilities,
    ) {
    }

    public function index(ServiceIndexRequest $request): JsonResponse
    {
        $query = Service::query()
            ->with(['organizationType', 'organization', 'media'])
            ->withCount(['organizations', 'serviceGroups']);

        if (!$request->user()?->isSuperAdmin() && !$request->user()?->isGlobalStaff()) {
            $organizationId = $request->user()?->organization_id;

            if (!$organizationId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin account is not assigned to an organization.',
                ], 403);
            }

            $query->where('organization_id', $organizationId);
        }

        ApiQueryBuilder::applySearch($query, $request->search(), $request->searchableColumns());

        if ($request->filled('filter.type_slug')) {
            $typeSlug = $request->string('filter.type_slug')->toString();
            $query->whereHas('organizationType', fn ($typeQuery) => $typeQuery->where('slug', $typeSlug));
        }

        if ($request->filled('filter.organization_id')) {
            $query->where('organization_id', $request->string('filter.organization_id')->toString());
        }

        if ($request->filled('filter.is_active')) {
            $query->where('is_active', $request->boolean('filter.is_active'));
        }

        ApiQueryBuilder::applyDateRangeFilter($query, 'created_at', $request->input('filter.created_at'));

        ApiQueryBuilder::applySort(
            $query,
            $request->sort(),
            $request->direction(),
            $request->allowedSorts(),
            'created_at'
        );

        $services = $query->paginate($request->perPage())->withQueryString();

        return $this->paginated(
            ServiceResource::collection($services),
            $services,
            'Services retrieved successfully.'
        );
    }

    public function forOrganization(ServiceIndexRequest $request, Organization $organization): JsonResponse
    {
        $query = Service::query()
            ->where(function ($serviceQuery) use ($organization): void {
                $serviceQuery
                    ->where('organization_id', $organization->id)
                    ->orWhereHas('organizations', fn ($organizationQuery) => $organizationQuery->whereKey($organization->id));
            })
            ->with(['organizationType', 'organization', 'media'])
            ->withCount(['organizations', 'serviceGroups']);

        ApiQueryBuilder::applySearch($query, $request->search(), $request->searchableColumns());

        if ($request->filled('filter.type_slug')) {
            $typeSlug = $request->string('filter.type_slug')->toString();
            $query->whereHas('organizationType', fn ($typeQuery) => $typeQuery->where('slug', $typeSlug));
        }

        if ($request->filled('filter.is_active')) {
            $query->where('is_active', $request->boolean('filter.is_active'));
        }

        ApiQueryBuilder::applyDateRangeFilter($query, 'created_at', $request->input('filter.created_at'));

        ApiQueryBuilder::applySort($query, $request->sort(), $request->direction(), $request->allowedSorts(), 'created_at');

        $services = $query->paginate($request->perPage())->withQueryString();

        return $this->paginated(
            ServiceResource::collection($services),
            $services,
            'Organization services retrieved successfully.'
        );
    }

    public function categories(Request $request): JsonResponse
    {
        $user = $request->user();
        $catalog = $user ? $this->planCapabilities->serviceCatalog($user) : collect();

        return $this->success([
            'active' => $user ? $this->planCapabilities->resolved($user)['active'] : false,
            'categories' => $catalog->map(fn (Service $service): array => [
                'slug' => $service->slug,
                'label' => $service->category ?: $service->name,
                'name' => $service->name,
            ])->values()->all(),
            'message' => $catalog->isEmpty()
                ? 'No service categories are available on the current subscription plan.'
                : null,
        ], 'Service categories retrieved successfully.');
    }

    public function map(Request $request): JsonResponse
    {
        $services = $this->serviceMapService->list($request);

        return $this->success(
            ServiceMapResource::collection($services)->resolve(),
            'Service coverage data retrieved successfully.'
        );
    }

    public function show(Request $request, Service $service): JsonResponse
    {
        abort_unless($this->canAccessService($request, $service), 403);

        $service->load(['organizationType', 'organization', 'media', 'activityLogs'])
            ->loadCount(['organizations', 'serviceGroups']);

        return $this->success(
            new ServiceResource($service),
            'Service retrieved successfully.'
        );
    }

    public function store(ServiceStoreRequest $request): JsonResponse
    {
        $service = DB::transaction(function () use ($request): Service {
            $this->lockOrganizationForActiveService($request);
            $this->demoteNewServiceWhenActiveLimitReached($request);
            $this->assertPlanEntitlements($request);
            $this->assertServiceAreaCapability($request);
            return Service::query()->create($this->buildPayload($request));
        });

        $this->recordActivity($request, $service, 'created', 'Service was created.');

        $this->storeMedia($service, $request);

        $service->load(['organizationType', 'organization', 'media', 'activityLogs'])
            ->loadCount(['organizations', 'serviceGroups']);

        if ($service->organization_id) {
            $this->notificationService->notifyAdminsForOrganisation(
                $service->organization_id,
                $this->notificationService->buildPayload(
                    'service_created',
                    'Service added',
                    sprintf('Service "%s" has been added.', $service->title ?? $service->name),
                    Service::class,
                    $service->id,
                    '/admin/services',
                    'normal',
                    $request->user()?->id,
                    $service->organization_id
                ),
                'Service added',
                'View service'
            );
        }

        if ($request->user()?->isSuperAdmin() || $request->user()?->isGlobalStaff()) {
            $this->notificationService->notifySuperAdmins(
                $this->notificationService->buildPayload(
                    'service_created',
                    'Service added',
                    sprintf('Service "%s" has been added.', $service->title ?? $service->name),
                    Service::class,
                    $service->id,
                    '/super-admin/services',
                    'normal',
                    $request->user()?->id,
                    $service->organization_id
                ),
                'Service added',
                'View service'
            );
        }

        return $this->created(
            new ServiceResource($service),
            'Service created successfully.'
        );
    }

    public function update(ServiceUpdateRequest $request, Service $service): JsonResponse
    {
        abort_unless($this->canAccessService($request, $service), 403);
        DB::transaction(function () use ($request, $service): void {
            $this->lockOrganizationForActiveService($request, $service);
            $this->assertPlanEntitlements($request, $service);
            $this->assertServiceAreaCapability($request, $service);
            $service->fill($this->buildPayload($request, $service));
            $service->save();
        });
        $this->recordActivity($request, $service, 'updated', 'Service details were updated.');
        $this->storeMedia($service, $request);
        $service->load(['organizationType', 'organization', 'media', 'activityLogs'])
            ->loadCount(['organizations', 'serviceGroups']);

        if ($service->organization_id) {
            $this->notificationService->notifyAdminsForOrganisation(
                $service->organization_id,
                $this->notificationService->buildPayload(
                    'service_updated',
                    'Service updated',
                    sprintf('Service "%s" has been updated.', $service->title ?? $service->name),
                    Service::class,
                    $service->id,
                    '/admin/services',
                    'normal',
                    $request->user()?->id,
                    $service->organization_id
                ),
                'Service updated',
                'View service'
            );
        }

        return $this->success(
            new ServiceResource($service),
            'Service updated successfully.'
        );
    }

    public function destroy(Request $request, Service $service): JsonResponse
    {
        abort_unless($this->canAccessService($request, $service), 403);

        $service->delete();

        return $this->success([], 'Service deleted successfully.');
    }

    private function buildPayload(Request $request, ?Service $service = null): array
    {
        $validated = $request->validated();

        $name = $validated['name'] ?? $service?->name;
        $title = $validated['title'] ?? $name;

        $payload = [
            'name' => $name,
            'title' => $title,
            'category' => $this->derivedCategory($request, $service),
            'slug' => $this->uniqueServiceSlug($validated['slug'] ?? null, $name, $service),
            'description' => $validated['description'] ?? $service?->description,
            'service_area' => $validated['service_area'] ?? $service?->service_area,
            'service_area_geometry' => $validated['service_area_geometry'] ?? $service?->service_area_geometry,
            'rate_from' => $validated['rate_from'] ?? $service?->rate_from,
            'rate_to' => $validated['rate_to'] ?? $service?->rate_to,
            'is_active' => array_key_exists('is_active', $request->all())
                ? $request->boolean('is_active')
                : (bool) ($service?->is_active ?? true),
        ];

        if ($service === null) {
            $payload['generated_id'] = $this->idGenerator->generate('services');
        }

        if ($request->user()?->isSuperAdmin() || $request->user()?->isGlobalStaff()) {
            $payload['organization_id'] = $validated['organization_id'] ?? $service?->organization_id;
            $payload['type_id'] = $validated['type_id'] ?? $service?->type_id;
        } else {
            $payload['organization_id'] = $request->user()?->organization_id;
            $payload['type_id'] = $request->user()?->organization?->type_id ?? $service?->type_id;
        }

        return $payload;
    }

    private function derivedCategory(Request $request, ?Service $service = null): ?string
    {
        $validated = $request->validated();
        $category = trim((string) ($validated['category'] ?? ''));

        return $category !== ''
            ? $category
            : ($service?->category ?: trim((string) ($validated['name'] ?? $service?->name ?? '')) ?: null);
    }

    private function uniqueServiceSlug(?string $requestedSlug, ?string $name, ?Service $service = null): string
    {
        if ($service && !request()->has('slug') && filled($service->slug)) {
            return (string) $service->slug;
        }

        $base = Str::slug(trim((string) ($requestedSlug ?: $name))) ?: 'service';
        $slug = $base;
        $suffix = 2;

        while (Service::withTrashed()
            ->when($service, fn ($query) => $query->where($service->getKeyName(), '!=', $service->getKey()))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function canAccessService(Request $request, Service $service): bool
    {
        $user = $request->user();

        if (!$user) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->isGlobalStaff()) {
            return true;
        }

        $organizationId = $user->organization_id;

        return (bool) $organizationId && $service->organization_id === $organizationId;
    }

    private function assertServiceAreaCapability(Request $request, ?Service $service = null): void
    {
        $user = $request->user();

        if (!$user || $user->isSuperAdmin() || $user->isGlobalStaff()) {
            return;
        }

        $validated = $request->validated();
        $areaProvided = array_key_exists('service_area', $validated);
        $geometryProvided = array_key_exists('service_area_geometry', $validated);

        if (!$areaProvided && !$geometryProvided) {
            return;
        }

        $requestedArea = $areaProvided ? ($validated['service_area'] ?: null) : $service?->service_area;
        $requestedGeometry = $geometryProvided ? ($validated['service_area_geometry'] ?: null) : $service?->service_area_geometry;

        // Updating unrelated service fields must not require a plan feature just
        // because an existing area is included in the edit form payload.
        if ($service && $requestedArea === $service->service_area
            && json_encode($requestedGeometry) === json_encode($service->service_area_geometry)) {
            return;
        }

        if (blank($requestedArea) && blank($requestedGeometry)) {
            return;
        }

        $feature = $this->planCapabilities->feature($user, 'Service Areas');
        abort_unless(
            $feature['enabled'],
            403,
            'Service area coverage is not included in your subscription plan.'
        );

        $limit = is_numeric($feature['value']) ? (int) $feature['value'] : null;
        if ($limit === null || !$user->organization_id) {
            return;
        }

        $requestedActive = array_key_exists('is_active', $request->all())
            ? $request->boolean('is_active')
            : (bool) ($service?->is_active ?? true);
        // Draft services may carry a future service area without consuming a
        // public service-area slot. The slot is counted when the service is
        // activated.
        if (!$requestedActive) {
            return;
        }

        $alreadyCounted = $service && $service->organization_id === $user->organization_id
            && (filled($service->service_area) || filled($service->service_area_geometry));

        $used = Service::query()
            ->where('organization_id', $user->organization_id)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNotNull('service_area_geometry')->orWhere(function ($areaQuery): void {
                    $areaQuery->whereNotNull('service_area')->whereRaw("TRIM(service_area) <> ''");
                });
            })
            ->count();

        abort_if(
            !$alreadyCounted && $used >= $limit,
            422,
            sprintf('Your subscription allows up to %d service area%s.', $limit, $limit === 1 ? '' : 's')
        );
    }

    private function lockOrganizationForActiveService(Request $request, ?Service $service = null): void
    {
        $user = $request->user();
        if (!$user || $user->isSuperAdmin() || $user->isGlobalStaff() || !$user->organization_id) {
            return;
        }

        $requestedActive = array_key_exists('is_active', $request->all())
            ? $request->boolean('is_active')
            : (bool) ($service?->is_active ?? true);
        if (!$requestedActive || ($service && $service->is_active)) {
            return;
        }

        $organization = \App\Models\Organization::query()
            ->with(['plan', 'currentSubscription'])
            ->lockForUpdate()
            ->findOrFail($user->organization_id);
        $user->setRelation('organization', $organization);
    }

    private function demoteNewServiceWhenActiveLimitReached(Request $request): void
    {
        $user = $request->user();
        if (!$user || $user->isSuperAdmin() || $user->isGlobalStaff() || !$user->organization_id) {
            return;
        }

        $requestedActive = array_key_exists('is_active', $request->all())
            ? $request->boolean('is_active')
            : true;
        if (!$requestedActive) {
            return;
        }

        $activeLimit = $this->planCapabilities->resolved($user)['limits']['active_services'] ?? null;
        if ($activeLimit === null) {
            return;
        }

        $activeServices = Service::query()
            ->where('organization_id', $user->organization_id)
            ->where('is_active', true)
            ->count();
        if ($activeServices >= (int) $activeLimit) {
            $request->merge(['is_active' => false]);
        }
    }

    private function assertPlanEntitlements(Request $request, ?Service $service = null): void
    {
        $user = $request->user();
        if (!$user || $user->isSuperAdmin() || $user->isGlobalStaff()) {
            return;
        }

        $entitlements = $this->planCapabilities->resolved($user);
        if (!$entitlements['active'] || !($entitlements['features']['service_management']['enabled'] ?? false)) {
            $this->planError('PLAN_SERVICE_MANAGEMENT_NOT_INCLUDED', 'Service Management is not included in your subscription plan.');
        }

        $organizationId = $user->organization_id;
        $requestedActive = array_key_exists('is_active', $request->all())
            ? $request->boolean('is_active')
            : (bool) ($service?->is_active ?? true);
        $activating = $requestedActive && (!$service || !$service->is_active);
        $activeLimit = $entitlements['limits']['active_services'];
        if ($activating && $activeLimit !== null) {
            $activeServices = Service::query()
                ->where('organization_id', $organizationId)
                ->where('is_active', true)
                ->count();
            if ($activeServices >= (int) $activeLimit) {
                $this->planError('PLAN_ACTIVE_SERVICE_LIMIT_REACHED', sprintf('Your %s plan allows up to %d active services. Deactivate another service or upgrade your plan.', $entitlements['plan']['name'] ?? 'current', $activeLimit));
            }
        }

        $images = count((array) $request->file('images', []));
        $videos = count((array) $request->file('videos', []));
        if ($videos > 0 && ($entitlements['features']['video_upload']['configured'] ?? false) && !($entitlements['features']['video_upload']['enabled'] ?? false)) {
            $this->planError('PLAN_VIDEO_NOT_INCLUDED', 'Video uploads are not included in your subscription plan.');
        }

        $existingImages = $service?->media()->where('media_type', 'image')->count() ?? 0;
        $existingVideos = $service?->media()->where('media_type', 'video')->count() ?? 0;
        $imageLimit = $entitlements['limits']['images'];
        $videoLimit = $entitlements['limits']['videos'];
        if (($entitlements['features']['maximum_images']['configured'] ?? false) && $imageLimit !== null && $existingImages + $images > (int) $imageLimit) {
            $this->planError('PLAN_IMAGE_LIMIT_REACHED', sprintf('Your %s plan allows a maximum of %d images. Remove an image or upgrade your plan.', $entitlements['plan']['name'] ?? 'current', $imageLimit));
        }
        if (($entitlements['features']['maximum_videos']['configured'] ?? false) && $videoLimit !== null && $existingVideos + $videos > (int) $videoLimit) {
            $this->planError('PLAN_VIDEO_LIMIT_REACHED', sprintf('Your %s plan allows a maximum of %d videos. Remove a video or upgrade your plan.', $entitlements['plan']['name'] ?? 'current', $videoLimit));
        }
    }

    private function planError(string $code, string $message): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'code' => $code,
            'message' => $message,
        ], 422));
    }

    private function storeMedia(Service $service, Request $request): void
    {
        $order = (int) ServiceMedia::query()->where('service_id', $service->id)->max('sort_order');

        foreach ((array) $request->file('images', []) as $index => $file) {
            $path = $file->storePublicly("services/{$service->id}/images", 'public');
            ServiceMedia::query()->create([
                'service_id' => $service->id,
                'file_url' => 'storage/'.ltrim($path, '/'),
                'media_type' => 'image',
                'is_primary' => $index === 0 && $order === 0,
                'sort_order' => ++$order,
            ]);
        }

        foreach ((array) $request->file('videos', []) as $file) {
            $path = $file->storePublicly("services/{$service->id}/videos", 'public');
            ServiceMedia::query()->create([
                'service_id' => $service->id,
                'file_url' => 'storage/'.ltrim($path, '/'),
                'media_type' => 'video',
                'sort_order' => ++$order,
            ]);
        }
    }

    private function recordActivity(Request $request, Service $service, string $action, string $description): void
    {
        if (!Schema::hasTable('activity_logs')) {
            return;
        }

        $user = $request->user();
        ActivityLog::query()->create([
            'causer_id' => $user?->id,
            'subject_id' => $service->id,
            'organization_id' => $service->organization_id,
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'user_email' => $user?->email,
            'user_role' => $user?->roles?->pluck('name')->implode(', '),
            'action' => $action,
            'module' => 'service',
            'description' => $description,
            'method' => $request->method(),
            'route' => $request->path(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
