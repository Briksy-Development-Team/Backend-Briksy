<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Controllers\Api\Concerns\AppliesOrganizationScope;
use App\Http\Requests\Api\SuperAdmin\PlanRequestIndexRequest;
use App\Http\Requests\Api\SuperAdmin\PlanRequestReviewRequest;
use App\Http\Requests\Api\SuperAdmin\PlanRequestStoreRequest;
use App\Http\Requests\Api\SuperAdmin\PlanRequestUpdateRequest;
use App\Http\Resources\SuperAdmin\PlanRequestResource;
use App\Models\Order;
use App\Models\PlanRequest as PlanRequestModel;
use App\Models\SubscriptionPlan;
use App\Services\DynamicIdGeneratorService;
use App\Services\InvoiceService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlanRequestController extends Controller
{
    use AppliesOrganizationScope;

    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly DynamicIdGeneratorService $idGenerator,
        private readonly InvoiceService $invoiceService
    )
    {
    }

    public function index(PlanRequestIndexRequest $request): JsonResponse
    {
        $query = $this->scopedQuery(PlanRequestModel::query()->with(['organization', 'plan', 'order.invoice', 'invoice']), $request);
        \App\Support\Query\ApiQueryBuilder::applySearch($query, $request->search(), $request->searchableColumns());
        \App\Support\Query\ApiQueryBuilder::applyFilters($query, $request->filters(), $request->allowedFilters());
        \App\Support\Query\ApiQueryBuilder::applySort($query, $request->sort(), $request->direction(), $request->allowedSorts(), 'created_at');

        $items = $query->paginate($request->perPage())->withQueryString();

        return $this->paginated(PlanRequestResource::collection($items)->resolve(), $items, 'Plan requests retrieved successfully.');
    }

    public function store(PlanRequestStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $organizationId = $validated['organization_id'] ?? null;
        $organizationId = $organizationId ?? $this->organizationId($request);
        $requestCode = $this->idGenerator->generate('plan_requests');

        $planRequest = PlanRequestModel::query()->create([
            'request_code' => $requestCode,
            'organization_id' => $organizationId,
            'requested_by' => $validated['requested_by'] ?? $request->user()?->id,
            'plan_id' => $validated['plan_id'] ?? null,
            'company_name' => $validated['company_name'] ?? null,
            'contact_name' => $validated['contact_name'] ?? null,
            'contact_email' => $validated['contact_email'] ?? null,
            'contact_phone' => $validated['contact_phone'] ?? null,
            'requested_plan_name' => $validated['requested_plan_name'] ?? null,
            'billing_cycle' => $validated['billing_cycle'] ?? null,
            'message' => $validated['message'] ?? null,
            'status' => $validated['status'] ?? 'pending',
            'admin_notes' => $validated['admin_notes'] ?? null,
        ]);

        $planRequest->load(['organization', 'plan', 'order.invoice', 'invoice']);

        if ($planRequest->organization_id) {
            $this->notificationService->notifySuperAdmins(
                $this->notificationService->buildPayload(
                    'plan_request_created',
                    'New plan request',
                    sprintf('A new plan request was created for "%s".', $planRequest->organization?->name ?? 'an organisation'),
                    PlanRequestModel::class,
                    $planRequest->id,
                    '/super-admin/plan-requests',
                    'high',
                    $request->user()?->id,
                    $planRequest->organization_id
                ),
                'New plan request',
                'Review request'
            );
        }

        return $this->created(new PlanRequestResource($planRequest), 'Plan request created successfully.');
    }

    public function show(Request $request, string $planRequest): JsonResponse
    {
        $model = $this->findPlanRequest($request, $planRequest);

        return $this->success(new PlanRequestResource($model->load(['organization', 'plan', 'requestedBy', 'reviewedBy', 'order.invoice', 'invoice'])), 'Plan request retrieved successfully.');
    }

    public function update(PlanRequestUpdateRequest $request, string $planRequest): JsonResponse
    {
        $model = $this->findPlanRequest($request, $planRequest);
        $validated = $request->validated();
        $model->fill($validated);
        $model->save();

        return $this->success(new PlanRequestResource($model->fresh()->load(['organization', 'plan', 'order.invoice', 'invoice'])), 'Plan request updated successfully.');
    }

    public function approve(PlanRequestReviewRequest $request, string $planRequest): JsonResponse
    {
        return $this->review($request, $planRequest, 'approved');
    }

    public function reject(PlanRequestReviewRequest $request, string $planRequest): JsonResponse
    {
        return $this->review($request, $planRequest, 'rejected');
    }

    public function destroy(Request $request, string $planRequest): JsonResponse
    {
        $model = $this->findPlanRequest($request, $planRequest);
        $model->delete();

        return $this->success([], 'Plan request deleted successfully.');
    }

    protected function findPlanRequest(Request $request, string $id): PlanRequestModel
    {
        $query = $this->scopedQuery(PlanRequestModel::query(), $request);
        $model = $query
            ->where(function ($builder) use ($id): void {
                $builder->where('request_code', $id)
                    ->orWhere($builder->getModel()->getQualifiedKeyName(), $id);
            })
            ->first();

        abort_unless($model, 404, 'Resource not found.');

        return $model;
    }

    protected function review(PlanRequestReviewRequest $request, string $planRequest, string $status): JsonResponse
    {
        $model = $this->findPlanRequest($request, $planRequest);
        $validated = $request->validated();

        DB::transaction(function () use ($model, $validated, $status, $request): void {
            $model->status = $status;
            $model->admin_notes = $validated['admin_notes'] ?? $model->admin_notes;
            $model->reviewed_by = $request->user()?->id;
            $model->reviewed_at = now();
            $model->save();

            $organizationId = $model->organization_id ?? ($validated['organization_id'] ?? null);
            $planId = $model->plan_id ?? ($validated['plan_id'] ?? null);

            if ($status === 'approved' && ($validated['create_order'] ?? true) && $organizationId && $planId) {
                $plan = SubscriptionPlan::query()->find($planId);
                if ($plan) {
                    $subtotal = (float) $plan->price;
                    $startsAt = now();
                    $endsAt = $model->billing_cycle === 'yearly' ? now()->addYear() : now()->addMonth();
                    $order = $model->order()->first();
                    if (!$order) {
                        $orderNumber = $this->idGenerator->generate('orders');
                        $order = Order::query()->create([
                            'order_number' => $orderNumber,
                            'reference_no' => $orderNumber,
                            'organization_id' => $organizationId,
                            'user_id' => $model->requested_by,
                            'plan_id' => $plan->id,
                            'plan_request_id' => $model->id,
                            'coupon_id' => null,
                            'subtotal' => $subtotal,
                            'discount_amount' => 0,
                            'tax_amount' => 0,
                            'total_amount' => $subtotal,
                            'currency' => 'AUD',
                            'billing_cycle' => $model->billing_cycle,
                            'payment_status' => 'paid',
                            'order_status' => 'active',
                            'payment_method' => 'manual',
                            'transaction_reference' => null,
                            'starts_at' => $startsAt,
                            'ends_at' => $endsAt,
                            'notes' => 'Generated from approved plan request ' . $model->id,
                        ]);
                    } else {
                        $order->fill([
                            'organization_id' => $organizationId,
                            'user_id' => $model->requested_by,
                            'plan_id' => $plan->id,
                            'plan_request_id' => $model->id,
                            'subtotal' => $subtotal,
                            'discount_amount' => 0,
                            'tax_amount' => 0,
                            'total_amount' => $subtotal,
                            'currency' => 'AUD',
                            'billing_cycle' => $model->billing_cycle,
                            'payment_status' => 'paid',
                            'order_status' => 'active',
                            'payment_method' => 'manual',
                            'transaction_reference' => null,
                            'starts_at' => $startsAt,
                            'ends_at' => $endsAt,
                            'notes' => 'Generated from approved plan request ' . $model->id,
                        ]);
                        $order->save();
                    }

                    DB::table('organizations')->where('id', $organizationId)->update([
                        'plan_id' => $plan->id,
                        'updated_at' => now(),
                    ]);

                    DB::table('subscriptions')->updateOrInsert(
                        ['organization_id' => $organizationId],
                        [
                            'id' => (string) Str::uuid(),
                            'subscription_plan_id' => $plan->id,
                            'stripe_subscription_id' => 'sub_' . strtoupper(Str::random(12)),
                            'status' => 'active',
                            'current_period_start' => $order->starts_at,
                            'current_period_end' => $order->ends_at,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );

                    $this->invoiceService->createForPlanRequest(
                        $model,
                        $order,
                        $plan,
                        $model->organization?->abn
                    );
                }
            }

            if ($organizationId) {
                $this->notificationService->notifyAdminsForOrganisation(
                    $organizationId,
                    $this->notificationService->buildPayload(
                        $status === 'approved' ? 'plan_request_approved' : 'plan_request_rejected',
                        $status === 'approved' ? 'Plan request approved' : 'Plan request rejected',
                        sprintf('Your plan request for "%s" has been %s.', $model->requested_plan_name ?? 'your plan', $status),
                        PlanRequestModel::class,
                        $model->id,
                        '/admin/plan-requests',
                        $status === 'approved' ? 'high' : 'normal',
                        $request->user()?->id,
                        $organizationId
                    ),
                    $status === 'approved' ? 'Plan request approved' : 'Plan request rejected',
                    'View requests'
                );
            }
        });

        return $this->success(
            new PlanRequestResource($model->fresh()->load(['organization', 'plan', 'order.invoice', 'invoice'])),
            "Plan request {$status} successfully."
        );
    }
}
