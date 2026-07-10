<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\SuperAdmin\OrganizationIndexRequest;
use App\Http\Requests\Api\SuperAdmin\OrganizationStoreRequest;
use App\Http\Requests\Api\SuperAdmin\OrganizationUpdateRequest;
use App\Http\Resources\SuperAdmin\OrganizationResource;
use App\Models\Organization;
use App\Services\NotificationService;
use App\Services\DynamicIdGeneratorService;
use App\Services\ReferralService;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly DynamicIdGeneratorService $idGenerator,
        private readonly ReferralService $referralService
    )
    {
    }

    public function index(OrganizationIndexRequest $request): JsonResponse
    {
        $query = Organization::query()->with(['organizationType', 'plan']);

        ApiQueryBuilder::applySearch($query, $request->search(), $request->searchableColumns());
        ApiQueryBuilder::applyFilters($query, $request->filters(), $request->allowedFilters());

        if ($request->filled('filter.type_slug')) {
            $typeSlug = $request->string('filter.type_slug')->toString();
            $query->whereHas('organizationType', fn ($typeQuery) => $typeQuery->where('slug', $typeSlug));
        }

        if ($request->filled('filter.service_slug')) {
            $serviceSlug = $request->string('filter.service_slug')->toString();
            $query->whereHas('services', fn ($serviceQuery) => $serviceQuery->where('services.slug', $serviceSlug));
        }

        if ($request->filled('filter.service_group_slug')) {
            $serviceGroupSlug = $request->string('filter.service_group_slug')->toString();
            $query->whereHas('serviceGroups', fn ($serviceGroupQuery) => $serviceGroupQuery->where('service_groups.slug', $serviceGroupSlug));
        }

        if ($request->filled('filter.business_type')) {
            $query->where('business_type', $request->string('filter.business_type')->toString());
        }

        if ($request->filled('filter.business_verification_status')) {
            $query->where('business_verification_status', $request->string('filter.business_verification_status')->toString());
        }

        ApiQueryBuilder::applySort($query, $request->sort(), $request->direction(), $request->allowedSorts(), 'created_at');

        $paginator = $query->paginate($request->perPage())->withQueryString();

        return $this->paginated(
            OrganizationResource::collection($paginator),
            $paginator,
            'Organizations retrieved successfully.'
        );
    }

    public function show(Organization $organization): JsonResponse
    {
        $organization->load(['organizationType', 'plan']);

        return $this->success(
            new OrganizationResource($organization),
            'Organization retrieved successfully.'
        );
    }

    public function store(OrganizationStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['abn'] = preg_replace('/\s+/', '', (string) ($validated['abn'] ?? ''));
        $validated['business_verification_status'] = $validated['business_verification_status'] ?? 'pending';
        $validated['generated_id'] = $this->idGenerator->generate('organizations');
        $validated['referral_code'] = $validated['referral_code'] ?? $this->referralService->generateCode();
        $organization = Organization::query()->create($validated);
        $organization->load(['organizationType', 'plan']);

        $this->notificationService->notifySuperAdmins(
            $this->notificationService->buildPayload(
                'company_created',
                'New company signup',
                sprintf('Company "%s" has been created.', $organization->name),
                Organization::class,
                $organization->id,
                "/super-admin/companies/{$organization->id}",
                'high',
                $request->user()?->id,
                $organization->id
            ),
            'New company signup',
            'Review company'
        );

        return $this->created(
            new OrganizationResource($organization),
            'Organization created successfully.'
        );
    }

    public function update(OrganizationUpdateRequest $request, Organization $organization): JsonResponse
    {
        $validated = $request->validated();

        if (array_key_exists('abn', $validated)) {
            $validated['abn'] = preg_replace('/\s+/', '', (string) $validated['abn']);
        }

        $organization->fill($validated);
        $organization->save();
        $organization->load(['organizationType', 'plan']);

        return $this->success(
            new OrganizationResource($organization),
            'Organization updated successfully.'
        );
    }

    public function destroy(Organization $organization): JsonResponse
    {
        $organization->delete();

        return $this->success([], 'Organization deactivated successfully.');
    }

    public function restore(string $organization): JsonResponse
    {
        $organizationModel = Organization::withTrashed()->findOrFail($organization);
        $organizationModel->restore();

        return $this->success(
            new OrganizationResource($organizationModel->fresh()->load(['organizationType', 'plan'])),
            'Organization activated successfully.'
        );
    }

    public function assignPlan(Request $request, Organization $organization): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => ['nullable', 'uuid', 'exists:subscription_plans,id'],
        ]);

        $organization->update([
            'plan_id' => $validated['plan_id'] ?? null,
        ]);

        $organization->load(['organizationType', 'plan']);

        $this->notificationService->notifyAdminsForOrganisation(
            $organization->id,
            $this->notificationService->buildPayload(
                'plan_changed',
                'Plan updated',
                sprintf('Your company plan has been updated for "%s".', $organization->name),
                Organization::class,
                $organization->id,
                "/admin/settings",
                'normal',
                $request->user()?->id,
                $organization->id
            ),
            'Plan updated',
            'Open settings'
        );

        return $this->success(
            new OrganizationResource($organization),
            'Plan assigned successfully.'
        );
    }

    public function approveVerification(Organization $organization): JsonResponse
    {
        $organization->update([
            'business_verification_status' => 'verified',
            'is_verified' => true,
        ]);

        $organization->load(['organizationType', 'plan']);

        $this->notificationService->notifyAdminsForOrganisation(
            $organization->id,
            $this->notificationService->buildPayload(
                'abn_verified',
                'ABN verification completed',
                sprintf('Your organisation "%s" has been verified.', $organization->name),
                Organization::class,
                $organization->id,
                "/admin/settings",
                'high',
                request()->user()?->id,
                $organization->id
            ),
            'ABN verified',
            'View company'
        );

        return $this->success(
            new OrganizationResource($organization),
            'Business verification approved successfully.'
        );
    }

    public function rejectVerification(Organization $organization): JsonResponse
    {
        $organization->update([
            'business_verification_status' => 'rejected',
            'is_verified' => false,
        ]);

        $organization->load(['organizationType', 'plan']);

        $this->notificationService->notifyAdminsForOrganisation(
            $organization->id,
            $this->notificationService->buildPayload(
                'abn_rejected',
                'ABN verification failed',
                sprintf('Your organisation "%s" verification was rejected.', $organization->name),
                Organization::class,
                $organization->id,
                "/admin/settings",
                'high',
                request()->user()?->id,
                $organization->id
            ),
            'ABN verification rejected',
            'View company'
        );

        return $this->success(
            new OrganizationResource($organization),
            'Business verification rejected successfully.'
        );
    }
}
