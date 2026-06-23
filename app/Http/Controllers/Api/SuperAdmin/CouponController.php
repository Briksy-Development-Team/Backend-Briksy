<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\SuperAdmin\CouponIndexRequest;
use App\Http\Requests\Api\SuperAdmin\CouponRequest;
use App\Http\Requests\Api\SuperAdmin\CouponValidateRequest;
use App\Http\Resources\SuperAdmin\CouponResource;
use App\Models\Coupon;
use App\Models\Order;
use App\Services\NotificationService;
use App\Support\Query\ApiQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CouponController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public function index(CouponIndexRequest $request): JsonResponse
    {
        $query = Coupon::query();
        ApiQueryBuilder::applySearch($query, $request->search(), $request->searchableColumns());
        ApiQueryBuilder::applyFilters($query, $request->filters(), $request->allowedFilters());
        ApiQueryBuilder::applySort($query, $request->sort(), $request->direction(), $request->allowedSorts(), 'created_at');

        $items = $query->paginate($request->perPage())->withQueryString();
        return $this->paginated(CouponResource::collection($items)->resolve(), $items, 'Coupons retrieved successfully.');
    }

    public function store(CouponRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['created_by'] = $request->user()?->id;
        $validated['status'] = $validated['status'] ?? 'active';
        $coupon = Coupon::query()->create($validated);

        $this->notificationService->notifySuperAdmins(
            $this->notificationService->buildPayload(
                'coupon_created',
                'Coupon created',
                sprintf('Coupon "%s" has been created.', $coupon->code),
                Coupon::class,
                $coupon->id,
                '/super-admin/coupons',
                'normal',
                $request->user()?->id,
                null
            ),
            'Coupon created',
            'View coupon'
        );

        return $this->created(new CouponResource($coupon), 'Coupon created successfully.');
    }

    public function show(string $coupon): JsonResponse
    {
        $model = Coupon::query()->findOrFail($coupon);
        return $this->success(new CouponResource($model), 'Coupon retrieved successfully.');
    }

    public function update(CouponRequest $request, string $coupon): JsonResponse
    {
        $model = Coupon::query()->findOrFail($coupon);
        $model->fill($request->validated());
        $model->save();
        return $this->success(new CouponResource($model->fresh()), 'Coupon updated successfully.');
    }

    public function activate(string $coupon): JsonResponse
    {
        $model = Coupon::query()->findOrFail($coupon);
        $model->status = 'active';
        $model->save();

        $this->notificationService->notifySuperAdmins(
            $this->notificationService->buildPayload(
                'coupon_activated',
                'Coupon activated',
                sprintf('Coupon "%s" is now active.', $model->code),
                Coupon::class,
                $model->id,
                '/super-admin/coupons',
                'normal',
                request()->user()?->id,
                null
            ),
            'Coupon activated',
            'View coupon'
        );

        return $this->success(new CouponResource($model), 'Coupon activated successfully.');
    }

    public function deactivate(string $coupon): JsonResponse
    {
        $model = Coupon::query()->findOrFail($coupon);
        $model->status = 'inactive';
        $model->save();

        $this->notificationService->notifySuperAdmins(
            $this->notificationService->buildPayload(
                'coupon_expired',
                'Coupon deactivated',
                sprintf('Coupon "%s" has been deactivated.', $model->code),
                Coupon::class,
                $model->id,
                '/super-admin/coupons',
                'normal',
                request()->user()?->id,
                null
            ),
            'Coupon deactivated',
            'View coupon'
        );

        return $this->success(new CouponResource($model), 'Coupon deactivated successfully.');
    }

    public function destroy(string $coupon): JsonResponse
    {
        $model = Coupon::query()->findOrFail($coupon);
        $model->delete();
        return $this->success([], 'Coupon deleted successfully.');
    }

    public function validateCoupon(CouponValidateRequest $request): JsonResponse
    {
        $coupon = Coupon::query()->where('code', $request->input('code'))->first();

        if (!$coupon) {
            return response()->json(['success' => false, 'message' => 'Coupon not found.'], 422);
        }

        $now = Carbon::now();
        if ($coupon->status !== 'active') {
            return response()->json(['success' => false, 'message' => 'Coupon is not active.'], 422);
        }

        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            return response()->json(['success' => false, 'message' => 'Coupon has not started yet.'], 422);
        }

        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            $coupon->status = 'expired';
            $coupon->save();
            return response()->json(['success' => false, 'message' => 'Coupon has expired.'], 422);
        }

        if ($coupon->usage_limit !== null && $coupon->usage_count >= $coupon->usage_limit) {
            return response()->json(['success' => false, 'message' => 'Coupon usage limit reached.'], 422);
        }

        $amount = (float) $request->input('amount');
        if ($coupon->min_order_amount !== null && $amount < (float) $coupon->min_order_amount) {
            return response()->json(['success' => false, 'message' => 'Order amount is below coupon minimum.'], 422);
        }

        if ($coupon->per_user_limit !== null && $request->filled('user_id')) {
            $userUses = Order::query()->where('coupon_id', $coupon->id)->where('user_id', $request->input('user_id'))->count();
            if ($userUses >= $coupon->per_user_limit) {
                return response()->json(['success' => false, 'message' => 'Coupon per-user limit reached.'], 422);
            }
        }

        $discount = $coupon->discount_type === 'percentage'
            ? ($amount * ((float) $coupon->discount_value / 100))
            : (float) $coupon->discount_value;

        if ($coupon->max_discount_amount !== null) {
            $discount = min($discount, (float) $coupon->max_discount_amount);
        }

        $discount = min($discount, $amount);

        return $this->success([
            'valid' => true,
            'coupon' => new CouponResource($coupon),
            'discount_amount' => round($discount, 2),
        ], 'Coupon is valid.');
    }
}
