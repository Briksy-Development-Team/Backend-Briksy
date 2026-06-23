<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\SuperAdmin\OrderIndexRequest;
use App\Http\Requests\Api\SuperAdmin\OrderRequest;
use App\Http\Resources\SuperAdmin\OrderResource;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\SubscriptionPlan;
use App\Http\Controllers\Api\Concerns\AppliesOrganizationScope;
use App\Services\NotificationService;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    use AppliesOrganizationScope;

    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public function index(OrderIndexRequest $request): JsonResponse
    {
        $query = $this->scopedQuery(Order::query()->with(['organization', 'user', 'plan', 'coupon']), $request);
        ApiQueryBuilder::applySearch($query, $request->search(), $request->searchableColumns());
        ApiQueryBuilder::applyFilters($query, $request->filters(), $request->allowedFilters());
        ApiQueryBuilder::applySort($query, $request->sort(), $request->direction(), $request->allowedSorts(), 'created_at');

        $items = $query->paginate($request->perPage())->withQueryString();
        return $this->paginated(OrderResource::collection($items)->resolve(), $items, 'Orders retrieved successfully.');
    }

    public function store(OrderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $plan = isset($validated['plan_id']) ? SubscriptionPlan::query()->find($validated['plan_id']) : null;
        $coupon = null;
        $discountAmount = (float) ($validated['discount_amount'] ?? 0);

        if (!empty($validated['coupon_id'])) {
            $coupon = Coupon::query()->find($validated['coupon_id']);
        } elseif (!empty($validated['coupon_code'])) {
            $coupon = Coupon::query()->where('code', $validated['coupon_code'])->first();
        }

        if ($coupon) {
            $amount = (float) $validated['subtotal'];
            $discountAmount = $coupon->discount_type === 'percentage'
                ? $amount * ((float) $coupon->discount_value / 100)
                : (float) $coupon->discount_value;
            if ($coupon->max_discount_amount !== null) {
                $discountAmount = min($discountAmount, (float) $coupon->max_discount_amount);
            }
            $discountAmount = min($discountAmount, $amount);
        }

        $subtotal = (float) $validated['subtotal'];
        $taxAmount = (float) ($validated['tax_amount'] ?? 0);
        $total = (float) ($validated['total_amount'] ?? max(0, $subtotal - $discountAmount + $taxAmount));

        $order = Order::query()->create([
            'order_number' => $validated['order_number'] ?? 'ORD-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
            'organization_id' => $validated['organization_id'] ?? $this->organizationId($request),
            'user_id' => $validated['user_id'] ?? $request->user()?->id,
            'plan_id' => $validated['plan_id'] ?? $plan?->id,
            'coupon_id' => $coupon?->id ?? $validated['coupon_id'] ?? null,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'total_amount' => $total,
            'currency' => $validated['currency'] ?? 'INR',
            'billing_cycle' => $validated['billing_cycle'] ?? null,
            'payment_status' => $validated['payment_status'] ?? 'pending',
            'order_status' => $validated['order_status'] ?? 'draft',
            'payment_method' => $validated['payment_method'] ?? null,
            'transaction_reference' => $validated['transaction_reference'] ?? null,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        if ($coupon) {
            $coupon->increment('usage_count');
        }

        if ($order->organization_id) {
            $this->notificationService->notifyAdminsForOrganisation(
                $order->organization_id,
                $this->notificationService->buildPayload(
                    'order_created',
                    'New order created',
                    sprintf('Order %s has been created.', $order->order_number),
                    Order::class,
                    $order->id,
                    '/admin/orders',
                    'normal',
                    $request->user()?->id,
                    $order->organization_id
                ),
                'New order created',
                'View order'
            );
        }

        return $this->created(new OrderResource($order->load(['organization', 'user', 'plan', 'coupon'])), 'Order created successfully.');
    }

    public function show(string $order): JsonResponse
    {
        $model = $this->scopedQuery(Order::query()->with(['organization', 'user', 'plan', 'coupon']), request())->whereKey($order)->firstOrFail();
        return $this->success(new OrderResource($model), 'Order retrieved successfully.');
    }

    public function update(OrderRequest $request, string $order): JsonResponse
    {
        $model = $this->scopedQuery(Order::query(), $request)->whereKey($order)->firstOrFail();
        $model->fill($request->validated());
        $model->save();
        return $this->success(new OrderResource($model->fresh()->load(['organization', 'user', 'plan', 'coupon'])), 'Order updated successfully.');
    }

    public function markPaid(string $order): JsonResponse
    {
        $model = $this->scopedQuery(Order::query(), request())->whereKey($order)->firstOrFail();
        $model->payment_status = 'paid';
        $model->order_status = 'active';
        $model->save();

        if ($model->organization_id) {
            $this->notificationService->notifyAdminsForOrganisation(
                $model->organization_id,
                $this->notificationService->buildPayload(
                    'payment_status_changed',
                    'Payment marked paid',
                    sprintf('Order %s was marked as paid.', $model->order_number),
                    Order::class,
                    $model->id,
                    '/admin/orders',
                    'normal',
                    request()->user()?->id,
                    $model->organization_id
                ),
                'Payment updated',
                'View order'
            );
        }

        return $this->success(new OrderResource($model->fresh()->load(['organization', 'user', 'plan', 'coupon'])), 'Order marked as paid.');
    }

    public function cancel(string $order): JsonResponse
    {
        $model = $this->scopedQuery(Order::query(), request())->whereKey($order)->firstOrFail();
        $model->payment_status = 'cancelled';
        $model->order_status = 'cancelled';
        $model->save();

        if ($model->organization_id) {
            $this->notificationService->notifyAdminsForOrganisation(
                $model->organization_id,
                $this->notificationService->buildPayload(
                    'payment_failed',
                    'Order cancelled',
                    sprintf('Order %s was cancelled.', $model->order_number),
                    Order::class,
                    $model->id,
                    '/admin/orders',
                    'high',
                    request()->user()?->id,
                    $model->organization_id
                ),
                'Order cancelled',
                'View order'
            );
        }

        return $this->success(new OrderResource($model->fresh()->load(['organization', 'user', 'plan', 'coupon'])), 'Order cancelled.');
    }

    public function destroy(string $order): JsonResponse
    {
        $model = $this->scopedQuery(Order::query(), request())->whereKey($order)->firstOrFail();
        $model->delete();
        return $this->success([], 'Order deleted successfully.');
    }
}
