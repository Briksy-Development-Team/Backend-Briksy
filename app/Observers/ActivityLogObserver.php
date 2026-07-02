<?php

namespace App\Observers;

use App\Models\CompanySetting;
use App\Models\Coupon;
use App\Models\EmailTemplate;
use App\Models\Organization;
use App\Models\Order;
use App\Models\PlanRequest;
use App\Models\PlatformSetting;
use App\Models\PropertyListing;
use App\Models\Service;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Database\Eloquent\Model;

class ActivityLogObserver
{
    public function __construct(private readonly ActivityLogService $activityLogService)
    {
    }

    public function created(Model $model): void
    {
        if (!$this->supports($model)) {
            return;
        }

        $this->activityLogService->logCreate($this->module($model), $model);
    }

    public function updated(Model $model): void
    {
        if (!$this->supports($model)) {
            return;
        }

        $changes = $this->changedAttributes($model);
        if ($changes === []) {
            return;
        }

        $this->activityLogService->logUpdate(
            $this->module($model),
            $model,
            $changes['old'],
            $changes['new']
        );
    }

    public function deleted(Model $model): void
    {
        if (!$this->supports($model)) {
            return;
        }

        $this->activityLogService->logDelete($this->module($model), $model);
    }

    private function supports(Model $model): bool
    {
        return match ($model::class) {
            Organization::class,
            PropertyListing::class,
            Service::class,
            User::class,
            SubscriptionPlan::class,
            PlanRequest::class,
            Coupon::class,
            Order::class,
            EmailTemplate::class,
            CompanySetting::class,
            PlatformSetting::class => true,
            default => false,
        };
    }

    private function module(Model $model): string
    {
        return match ($model::class) {
            Organization::class => 'organizations',
            PropertyListing::class => 'properties',
            Service::class => 'services',
            User::class => 'users',
            SubscriptionPlan::class => 'plans',
            PlanRequest::class => 'plan_requests',
            Coupon::class => 'coupons',
            Order::class => 'orders',
            EmailTemplate::class => 'email_templates',
            CompanySetting::class, PlatformSetting::class => 'settings',
            default => 'system',
        };
    }

    private function changedAttributes(Model $model): array
    {
        $ignore = ['updated_at', 'deleted_at', 'created_at'];
        $old = [];
        $new = [];

        foreach ($model->getChanges() as $key => $value) {
            if (in_array($key, $ignore, true)) {
                continue;
            }

            $old[$key] = $model->getOriginal($key);
            $new[$key] = $value;
        }

        if ($old === [] && $new === []) {
            return [];
        }

        return [
            'old' => $old,
            'new' => $new,
        ];
    }
}
