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
use App\Services\DynamicIdGeneratorService;
use App\Services\NotificationService;
use App\Services\ServiceMapService;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ServiceController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly DynamicIdGeneratorService $idGenerator,
        private readonly ServiceMapService $serviceMapService,
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

        ApiQueryBuilder::applySort($query, $request->sort(), $request->direction(), $request->allowedSorts(), 'created_at');

        $services = $query->paginate($request->perPage())->withQueryString();

        return $this->paginated(
            ServiceResource::collection($services),
            $services,
            'Organization services retrieved successfully.'
        );
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

        $service->load(['organizationType', 'organization', 'media'])
            ->loadCount(['organizations', 'serviceGroups']);

        return $this->success(
            new ServiceResource($service),
            'Service retrieved successfully.'
        );
    }

    public function store(ServiceStoreRequest $request): JsonResponse
    {
        $service = Service::query()->create($this->buildPayload($request));

        $this->storeMedia($service, $request);

        $service->load(['organizationType', 'organization', 'media'])
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

        $service->fill($this->buildPayload($request, $service));
        $service->save();
        $this->storeMedia($service, $request);
        $service->load(['organizationType', 'organization', 'media'])
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
            'category' => $validated['category'] ?? $service?->category,
            'slug' => $validated['slug'] ?? $service?->slug,
            'generated_id' => $service?->generated_id ?? $this->idGenerator->generate('services'),
            'description' => $validated['description'] ?? $service?->description,
            'service_area' => $validated['service_area'] ?? $service?->service_area,
            'service_area_geometry' => $validated['service_area_geometry'] ?? $service?->service_area_geometry,
            'rate_from' => $validated['rate_from'] ?? $service?->rate_from,
            'rate_to' => $validated['rate_to'] ?? $service?->rate_to,
            'is_active' => array_key_exists('is_active', $validated)
                ? (bool) $validated['is_active']
                : (bool) ($service?->is_active ?? true),
        ];

        if ($request->user()?->isSuperAdmin() || $request->user()?->isGlobalStaff()) {
            $payload['organization_id'] = $validated['organization_id'] ?? $service?->organization_id;
            $payload['type_id'] = $validated['type_id'] ?? $service?->type_id;
        } else {
            $payload['organization_id'] = $request->user()?->organization_id;
            $payload['type_id'] = $request->user()?->organization?->type_id ?? $service?->type_id;
        }

        return $payload;
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
}
