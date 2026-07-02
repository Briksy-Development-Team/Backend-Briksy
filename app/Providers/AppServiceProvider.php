<?php

namespace App\Providers;

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
use App\Observers\ActivityLogObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Database\Eloquent\Model::creating(function ($model) {
            if (!$model->getKey()) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });

        foreach ([
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
            PlatformSetting::class,
        ] as $modelClass) {
            $modelClass::observe(ActivityLogObserver::class);
        }
    }
}
