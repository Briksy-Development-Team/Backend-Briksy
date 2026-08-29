<?php

use App\Http\Controllers\Api\Auth\SocialAuthController;
use App\Http\Controllers\Api\Auth\AbnVerificationController;
use App\Http\Controllers\Api\Seeker\FavoriteController;
use App\Http\Controllers\Api\Admin\SeekerController as AdminSeekerController;
use App\Http\Controllers\Api\Admin\OrganizationController as AdminOrganizationController;
use App\Http\Controllers\Api\Admin\StaffController as AdminStaffController;
use App\Http\Controllers\Api\Admin\PlanRequestController as AdminPlanRequestController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\Api\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Api\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Api\Admin\ActivityLogController as AdminActivityLogController;
use App\Http\Controllers\Api\Admin\WebhookController as AdminWebhookController;
use App\Http\Controllers\Api\Admin\ReferralController as AdminReferralController;
use App\Http\Controllers\Api\Seeker\InquiryController;
use App\Http\Controllers\Api\Seeker\OrganizationSearchController;
use App\Http\Controllers\Api\Seeker\PropertySearchController;
use App\Http\Controllers\Api\Seeker\RegistrationController;
use App\Http\Controllers\Api\Seeker\ReviewController;
use App\Http\Controllers\Api\Seeker\SeekerProfileController;
use App\Http\Controllers\Api\Seeker\SeekerSavedSearchController;
use App\Http\Controllers\Api\SuperAdmin\OrganizationController;
use App\Http\Controllers\Api\SuperAdmin\OrganizationTypeController;
use App\Http\Controllers\Api\SuperAdmin\DashboardController;
use App\Http\Controllers\Api\SuperAdmin\PlanRequestController;
use App\Http\Controllers\Api\SuperAdmin\CouponController;
use App\Http\Controllers\Api\SuperAdmin\OrderController;
use App\Http\Controllers\Api\SuperAdmin\EmailTemplateController;
use App\Http\Controllers\Api\SuperAdmin\ServiceController as SuperAdminServiceController;
use App\Http\Controllers\Api\SuperAdmin\ServiceImportController as SuperAdminServiceImportController;
use App\Http\Controllers\Api\SuperAdmin\SettingController;
use App\Http\Controllers\Api\SuperAdmin\PermissionController as SuperAdminPermissionController;
use App\Http\Controllers\Api\SuperAdmin\PropertyController as SuperAdminPropertyController;
use App\Http\Controllers\Api\SuperAdmin\PropertyOfferController as SuperAdminPropertyOfferController;
use App\Http\Controllers\Api\SuperAdmin\PropertyMapController as SuperAdminPropertyMapController;
use App\Http\Controllers\Api\SuperAdmin\SubscriptionPlanController as SuperAdminSubscriptionPlanController;
use App\Http\Controllers\Api\SuperAdmin\DynamicIdSettingController as SuperAdminDynamicIdSettingController;
use App\Http\Controllers\Api\SuperAdmin\AddonController as SuperAdminAddonController;
use App\Http\Controllers\Api\SuperAdmin\SubscriptionController as SuperAdminSubscriptionController;
use App\Http\Controllers\Api\SuperAdmin\ActivityLogController as SuperAdminActivityLogController;
use App\Http\Controllers\Api\SuperAdmin\ReferralController as SuperAdminReferralController;
use App\Http\Controllers\Api\SuperAdmin\SeekerController as SuperAdminSeekerController;
use App\Http\Controllers\Api\SuperAdmin\StaffController;
use App\Http\Controllers\Api\Admin\PropertyController as AdminPropertyController;
use App\Http\Controllers\Api\Admin\BuyerBriefController;
use App\Http\Controllers\Api\Admin\BuilderProjectController;
use App\Http\Controllers\Api\Admin\PropertyOfferController as AdminPropertyOfferController;
use App\Http\Controllers\Api\Admin\PropertyImportController as AdminPropertyImportController;
use App\Http\Controllers\Api\Admin\BillingController as AdminBillingController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\NotificationPreferenceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->get('me/permissions', [SuperAdminPermissionController::class, 'me']);

Route::prefix('auth')->group(function (): void {
    Route::post('verify-abn', [AbnVerificationController::class, 'store'])->middleware('throttle:abn-verify');
    Route::post('social/{provider}', [SocialAuthController::class, 'login']);
});

Route::get('settings/public', [SettingController::class, 'publicSettings']);
Route::post('stripe/webhook', [StripeWebhookController::class, 'handle']);
Route::get('media/{media}', [MediaController::class, 'show'])->name('media.show');
Route::middleware('auth:sanctum')->delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');

Route::prefix('seeker')->group(function (): void {
    Route::post('auth/register', [RegistrationController::class, 'store']);
    Route::post('auth/login', [RegistrationController::class, 'loginSeeker']);

    Route::get('properties', [PropertySearchController::class, 'index']);
    Route::get('properties/{propertyListing}', [PropertySearchController::class, 'show']);

    Route::get('organizations', [OrganizationSearchController::class, 'index']);
    Route::get('organizations/{organization}', [OrganizationSearchController::class, 'show']);

    Route::post('inquiries', [InquiryController::class, 'store']);

    Route::middleware(['auth:sanctum', 'role:seeker'])->group(function (): void {
        Route::get('auth/me', [RegistrationController::class, 'me']);
        Route::post('auth/logout', [RegistrationController::class, 'logoutSeeker']);

        Route::get('favorites', [FavoriteController::class, 'index']);
        Route::post('favorites', [FavoriteController::class, 'store']);
        Route::post('favorites/toggle', [FavoriteController::class, 'toggle']);
        Route::delete('favorites/{favorite}', [FavoriteController::class, 'destroy']);

        Route::get('reviews', [ReviewController::class, 'index']);
        Route::post('reviews', [ReviewController::class, 'store']);

        Route::get('inquiries', [InquiryController::class, 'index']);
        Route::get('inquiries/{inquiry}', [InquiryController::class, 'show']);

        Route::get('profile', [SeekerProfileController::class, 'show']);
        Route::put('profile', [SeekerProfileController::class, 'update']);

        Route::get('saved-searches', [SeekerSavedSearchController::class, 'index']);
        Route::post('saved-searches', [SeekerSavedSearchController::class, 'store']);
        Route::get('saved-searches/{savedSearch}', [SeekerSavedSearchController::class, 'show']);
        Route::put('saved-searches/{savedSearch}', [SeekerSavedSearchController::class, 'update']);
        Route::delete('saved-searches/{savedSearch}', [SeekerSavedSearchController::class, 'destroy']);
    });
});

Route::prefix('admin')->group(function (): void {
    Route::post('auth/register', [RegistrationController::class, 'registerAdmin']);
    Route::post('auth/login', [RegistrationController::class, 'loginAdmin']);

    Route::middleware(['auth:sanctum', 'role:admin,admin_staff'])->group(function (): void {
        Route::get('dashboard', [AdminDashboardController::class, 'index'])->middleware('permission:dashboard.view');
    });

    Route::middleware(['auth:sanctum', 'role:admin,admin_staff'])->prefix('billing')->group(function (): void {
        Route::get('current-subscription', [AdminBillingController::class, 'currentSubscription']);
        Route::get('plans', [AdminBillingController::class, 'plans']);
        Route::get('addons', [AdminBillingController::class, 'addons']);
        Route::post('checkout', [AdminBillingController::class, 'checkout']);
        Route::get('checkout/{checkoutSessionId}', [AdminBillingController::class, 'verifyCheckoutSession']);
        Route::get('subscriptions', [AdminBillingController::class, 'subscriptions']);
    });

    Route::middleware(['auth:sanctum', 'role:admin,admin_staff', 'subscription'])->group(function (): void {
        Route::get('auth/me', [RegistrationController::class, 'me']);
        Route::post('auth/logout', [RegistrationController::class, 'logoutSeeker']);

        Route::get('plans', [AdminSubscriptionController::class, 'index']);
        Route::post('plans/{subscriptionPlan}/select', [AdminSubscriptionController::class, 'select']);
        Route::get('subscription', [AdminSubscriptionController::class, 'show']);

        Route::get('plan-requests', [AdminPlanRequestController::class, 'index'])->middleware('permission:plan_request.view');
        Route::post('plan-requests', [AdminPlanRequestController::class, 'store'])->middleware('permission:plan_request.create');
        Route::get('plan-requests/{planRequest}', [AdminPlanRequestController::class, 'show'])->middleware('permission:plan_request.view');

        Route::get('orders', [AdminOrderController::class, 'index'])->middleware('permission:order.view');
        Route::post('orders', [AdminOrderController::class, 'store'])->middleware('permission:order.create');
        Route::get('orders/{order}', [AdminOrderController::class, 'show'])->middleware('permission:order.view');
        Route::post('orders/{order}/cancel', [AdminOrderController::class, 'cancel'])->middleware('permission:order.cancel');

        Route::get('settings', [AdminSettingController::class, 'index'])->middleware('permission:settings.view');
        Route::patch('settings', [AdminSettingController::class, 'update'])->middleware('permission:settings.update');
        Route::prefix('webhooks')->group(function (): void {
            Route::get('/', [AdminWebhookController::class, 'index'])->middleware('permission:webhook.view');
            Route::get('stats', [AdminWebhookController::class, 'stats'])->middleware('permission:webhook.view');
            Route::post('/', [AdminWebhookController::class, 'store'])->middleware('permission:webhook.create');
            Route::get('logs', [AdminWebhookController::class, 'logs'])->middleware('permission:webhook.view');
            Route::get('logs/export', [AdminWebhookController::class, 'exportLogs'])->middleware('permission:webhook.view');
            Route::get('logs/{webhookDeliveryLog}', [AdminWebhookController::class, 'showLog'])->middleware('permission:webhook.view');
            Route::post('logs/{webhookDeliveryLog}/retry', [AdminWebhookController::class, 'retry'])->middleware('permission:webhook.retry');
            Route::post('{webhookEndpoint}/test', [AdminWebhookController::class, 'test'])->middleware('permission:webhook.retry');
            Route::post('{webhookEndpoint}/regenerate-secret', [AdminWebhookController::class, 'regenerateSecret'])->middleware('permission:webhook.update');
            Route::get('{webhookEndpoint}', [AdminWebhookController::class, 'show'])->middleware('permission:webhook.view');
            Route::put('{webhookEndpoint}', [AdminWebhookController::class, 'update'])->middleware('permission:webhook.update');
            Route::delete('{webhookEndpoint}', [AdminWebhookController::class, 'destroy'])->middleware('permission:webhook.delete');
        });

        Route::post('coupons/validate', [AdminCouponController::class, 'validateCoupon'])->middleware('permission:coupon.view');

        Route::get('seekers', [AdminSeekerController::class, 'index'])->middleware('permission:user.view');
        Route::get('seekers/{user}', [AdminSeekerController::class, 'show'])->middleware('permission:user.view');

        Route::get('businesses', [AdminOrganizationController::class, 'index'])->middleware('permission:company.view');
        Route::get('businesses/{organization}', [AdminOrganizationController::class, 'show'])->middleware('permission:company.view');
        Route::put('businesses/{organization}', [AdminOrganizationController::class, 'update'])->middleware('permission:company.update');

        Route::get('properties', [AdminPropertyController::class, 'index'])->middleware(['module:property_management', 'permission:property.view']);
        Route::get('properties/map', [AdminPropertyController::class, 'map'])->middleware(['module:property_management', 'permission:property.view']);
        Route::post('properties', [AdminPropertyController::class, 'store'])->middleware(['module:property_management', 'permission:property.create']);
        Route::get('properties/import/meta', [AdminPropertyImportController::class, 'meta'])->middleware(['module:property_management', 'permission:property.create']);
        Route::get('properties/import/template', [AdminPropertyImportController::class, 'template'])->middleware(['module:property_management', 'permission:property.create']);
        Route::post('properties/import', [AdminPropertyImportController::class, 'analyze'])->middleware(['module:property_management', 'permission:property.create']);
        Route::post('properties/imports/{propertyImport}/preview', [AdminPropertyImportController::class, 'preview'])->middleware(['module:property_management', 'permission:property.create']);
        Route::post('properties/imports/{propertyImport}/start', [AdminPropertyImportController::class, 'start'])->middleware(['module:property_management', 'permission:property.create']);
        Route::get('properties/imports/{propertyImport}', [AdminPropertyImportController::class, 'show'])->middleware(['module:property_management', 'permission:property.create']);
        Route::get('properties/imports/{propertyImport}/error-report', [AdminPropertyImportController::class, 'errorReport'])->middleware(['module:property_management', 'permission:property.create']);
        Route::get('properties/{propertyListing}', [AdminPropertyController::class, 'show'])->middleware(['module:property_management', 'permission:property.view']);
        Route::put('properties/{propertyListing}', [AdminPropertyController::class, 'update'])->middleware(['module:property_management', 'permission:property.update']);
        Route::delete('properties/{propertyListing}', [AdminPropertyController::class, 'destroy'])->middleware(['module:property_management', 'permission:property.delete']);
        Route::get('property-offers', [AdminPropertyOfferController::class, 'index'])->middleware(['module:property_management', 'permission:property.view']);
        Route::post('property-offers', [AdminPropertyOfferController::class, 'store'])->middleware(['module:property_management', 'permission:property.create']);
        Route::get('property-offers/{propertyOffer}', [AdminPropertyOfferController::class, 'show'])->middleware(['module:property_management', 'permission:property.view']);
        Route::put('property-offers/{propertyOffer}', [AdminPropertyOfferController::class, 'update'])->middleware(['module:property_management', 'permission:property.update']);
        Route::patch('property-offers/{propertyOffer}/toggle', [AdminPropertyOfferController::class, 'toggle'])->middleware(['module:property_management', 'permission:property.update']);
        Route::delete('property-offers/{propertyOffer}', [AdminPropertyOfferController::class, 'destroy'])->middleware(['module:property_management', 'permission:property.delete']);

        Route::get('buyer-briefs', [BuyerBriefController::class, 'index'])->middleware('module:buyer_management');
        Route::post('buyer-briefs', [BuyerBriefController::class, 'store'])->middleware('module:buyer_management');
        Route::get('builder-projects', [BuilderProjectController::class, 'index'])->middleware('module:builder_management');
        Route::post('builder-projects', [BuilderProjectController::class, 'store'])->middleware('module:builder_management');

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::patch('notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::delete('notifications/{notification}', [NotificationController::class, 'destroy']);
        Route::get('notification-preferences', [NotificationPreferenceController::class, 'show']);
        Route::patch('notification-preferences', [NotificationPreferenceController::class, 'update']);
        Route::get('activity-logs', [AdminActivityLogController::class, 'index'])->middleware('permission:activity_logs.view');
        Route::get('activity-logs/{activityLog}', [AdminActivityLogController::class, 'show'])->middleware('permission:activity_logs.view');
        Route::get('referrals', [AdminReferralController::class, 'index'])->middleware('permission:referral.view');

        Route::get('services/map', [SuperAdminServiceController::class, 'map'])->middleware(['module:service_management', 'permission:service.view']);
        Route::get('services/import/meta', [SuperAdminServiceImportController::class, 'meta'])->middleware(['module:service_management', 'permission:service.create']);
        Route::get('services/import/template', [SuperAdminServiceImportController::class, 'template'])->middleware(['module:service_management', 'permission:service.create']);
        Route::post('services/import', [SuperAdminServiceImportController::class, 'analyze'])->middleware(['module:service_management', 'permission:service.create']);
        Route::post('services/imports/{bulkImport}/preview', [SuperAdminServiceImportController::class, 'preview'])->middleware(['module:service_management', 'permission:service.create']);
        Route::post('services/imports/{bulkImport}/start', [SuperAdminServiceImportController::class, 'start'])->middleware(['module:service_management', 'permission:service.create']);
        Route::get('services/imports/{bulkImport}', [SuperAdminServiceImportController::class, 'show'])->middleware(['module:service_management', 'permission:service.create']);
        Route::get('services/imports/{bulkImport}/error-report', [SuperAdminServiceImportController::class, 'errorReport'])->middleware(['module:service_management', 'permission:service.create']);
        Route::get('services', [SuperAdminServiceController::class, 'index'])->middleware(['module:service_management', 'permission:service.view']);
        Route::post('services', [SuperAdminServiceController::class, 'store'])->middleware(['module:service_management', 'permission:service.create']);
        Route::get('services/{service}', [SuperAdminServiceController::class, 'show'])->middleware(['module:service_management', 'permission:service.view']);
        Route::put('services/{service}', [SuperAdminServiceController::class, 'update'])->middleware(['module:service_management', 'permission:service.update']);
        Route::delete('services/{service}', [SuperAdminServiceController::class, 'destroy'])->middleware(['module:service_management', 'permission:service.delete']);
    });

    Route::middleware(['auth:sanctum', 'role:admin'])->group(function (): void {
        Route::post('auth/register-staff', [RegistrationController::class, 'registerAdminStaff'])
            ->withoutMiddleware('role:admin');

        Route::get('staff/defaults', [AdminStaffController::class, 'defaults'])->middleware(['module:user_management', 'permission:user.view']);
        Route::get('staff', [AdminStaffController::class, 'index'])->middleware(['module:user_management', 'permission:user.view']);
        Route::post('staff', [AdminStaffController::class, 'store'])->middleware(['module:user_management', 'permission:user.create']);
        Route::get('staff/{user}', [AdminStaffController::class, 'show'])->middleware(['module:user_management', 'permission:user.view']);
        Route::put('staff/{user}', [AdminStaffController::class, 'update'])->middleware(['module:user_management', 'permission:user.update']);
        Route::delete('staff/{user}', [AdminStaffController::class, 'destroy'])->middleware(['module:user_management', 'permission:user.delete']);

    });
});

Route::prefix('super-admin')->group(function (): void {
    Route::post('auth/register', [RegistrationController::class, 'registerSuperAdmin']);
    Route::post('auth/login', [RegistrationController::class, 'loginSuperAdmin']);

    Route::middleware(['auth:sanctum', 'role:super_admin,super_admin_employee'])->group(function (): void {
        Route::get('auth/me', [RegistrationController::class, 'me']);
        Route::post('auth/logout', [RegistrationController::class, 'logoutSeeker']);

        Route::get('dynamic-id-settings', [SuperAdminDynamicIdSettingController::class, 'index'])->middleware('permission:dynamic_id.view');
        Route::post('dynamic-id-settings', [SuperAdminDynamicIdSettingController::class, 'store'])->middleware('permission:dynamic_id.manage');
        Route::get('dynamic-id-settings/{dynamicIdSetting}', [SuperAdminDynamicIdSettingController::class, 'show'])->middleware('permission:dynamic_id.view');
        Route::put('dynamic-id-settings/{dynamicIdSetting}', [SuperAdminDynamicIdSettingController::class, 'update'])->middleware('permission:dynamic_id.manage');
        Route::delete('dynamic-id-settings/{dynamicIdSetting}', [SuperAdminDynamicIdSettingController::class, 'destroy'])->middleware('permission:dynamic_id.manage');

        Route::get('addons', [SuperAdminAddonController::class, 'index'])->middleware('permission:addon.view');
        Route::post('addons', [SuperAdminAddonController::class, 'store'])->middleware('permission:addon.create');
        Route::get('addons/{addon}', [SuperAdminAddonController::class, 'show'])->middleware('permission:addon.view');
        Route::put('addons/{addon}', [SuperAdminAddonController::class, 'update'])->middleware('permission:addon.update');
        Route::patch('addons/{addon}/toggle', [SuperAdminAddonController::class, 'toggle'])->middleware('permission:addon.update');
        Route::delete('addons/{addon}', [SuperAdminAddonController::class, 'destroy'])->middleware('permission:addon.delete');

        Route::get('seekers', [SuperAdminSeekerController::class, 'index'])->middleware('permission:user.view');
        Route::post('seekers', [SuperAdminSeekerController::class, 'store'])->middleware('permission:user.create');
        Route::get('seekers/{seeker}', [SuperAdminSeekerController::class, 'show'])->middleware('permission:user.view');
        Route::put('seekers/{seeker}', [SuperAdminSeekerController::class, 'update'])->middleware('permission:user.update');
        Route::delete('seekers/{seeker}', [SuperAdminSeekerController::class, 'destroy'])->middleware('permission:user.delete');

        Route::get('staff/defaults', [StaffController::class, 'defaults'])->middleware('permission:user.view');
        Route::get('staff', [StaffController::class, 'index'])->middleware('permission:user.view');
        Route::post('staff', [StaffController::class, 'store'])->middleware('permission:user.create');
        Route::get('staff/{staff}', [StaffController::class, 'show'])->middleware('permission:user.view');
        Route::put('staff/{staff}', [StaffController::class, 'update'])->middleware('permission:user.update');
        Route::delete('staff/{staff}', [StaffController::class, 'destroy'])->middleware('permission:user.delete');

        Route::get('organizations', [OrganizationController::class, 'index'])->middleware('permission:company.view');
        Route::post('organizations', [OrganizationController::class, 'store'])->middleware('permission:company.create');
        Route::get('organizations/{organization}', [OrganizationController::class, 'show'])->middleware('permission:company.view');
        Route::put('organizations/{organization}', [OrganizationController::class, 'update'])->middleware('permission:company.update');
        Route::delete('organizations/{organization}', [OrganizationController::class, 'destroy'])->middleware('permission:company.delete');
        Route::patch('organizations/{organization}/restore', [OrganizationController::class, 'restore'])->middleware('permission:company.update');
        Route::patch('organizations/{organization}/assign-plan', [OrganizationController::class, 'assignPlan'])->middleware('permission:company.update');
        Route::patch('organizations/{organization}/approve-verification', [OrganizationController::class, 'approveVerification'])->middleware('permission:company.update');
        Route::patch('organizations/{organization}/reject-verification', [OrganizationController::class, 'rejectVerification'])->middleware('permission:company.update');

        Route::get('organization-types', [OrganizationTypeController::class, 'index'])->middleware('permission:company.view');
        Route::post('organization-types', [OrganizationTypeController::class, 'store'])->middleware('permission:company.create');
        Route::put('organization-types/{organizationType}', [OrganizationTypeController::class, 'update'])->middleware('permission:company.update');

        Route::get('properties', [SuperAdminPropertyController::class, 'index'])->middleware('permission:property.view');
        Route::get('properties/map', [SuperAdminPropertyController::class, 'map'])->middleware('permission:property.view');
        Route::get('properties/{propertyListing}', [SuperAdminPropertyController::class, 'show'])->middleware('permission:property.view');
        Route::patch('properties/{propertyListing}/approve', [SuperAdminPropertyController::class, 'approve'])->middleware('permission:property.approve');
        Route::patch('properties/{propertyListing}/reject', [SuperAdminPropertyController::class, 'reject'])->middleware('permission:property.reject');
        Route::patch('properties/{propertyListing}/publish', [SuperAdminPropertyController::class, 'publish'])->middleware('permission:property.publish');
        Route::patch('properties/{propertyListing}/archive', [SuperAdminPropertyController::class, 'archive'])->middleware('permission:property.archive');
        Route::patch('properties/{propertyListing}/verify-location', [SuperAdminPropertyController::class, 'verifyLocation'])->middleware('permission:property.verify_location');
        Route::patch('properties/{propertyListing}/unverify-location', [SuperAdminPropertyController::class, 'unverifyLocation'])->middleware('permission:property.unverify_location');
        Route::get('property-offers', [SuperAdminPropertyOfferController::class, 'index'])->middleware('permission:property.view');
        Route::post('property-offers', [SuperAdminPropertyOfferController::class, 'store'])->middleware('permission:property.create');
        Route::get('property-offers/{propertyOffer}', [SuperAdminPropertyOfferController::class, 'show'])->middleware('permission:property.view');
        Route::put('property-offers/{propertyOffer}', [SuperAdminPropertyOfferController::class, 'update'])->middleware('permission:property.update');
        Route::patch('property-offers/{propertyOffer}/toggle', [SuperAdminPropertyOfferController::class, 'toggle'])->middleware('permission:property.update');
        Route::delete('property-offers/{propertyOffer}', [SuperAdminPropertyOfferController::class, 'destroy'])->middleware('permission:property.delete');

        Route::get('services/map', [SuperAdminServiceController::class, 'map'])->middleware('permission:service.view');
        Route::get('services/import/meta', [SuperAdminServiceImportController::class, 'meta'])->middleware('permission:service.create');
        Route::get('services/import/template', [SuperAdminServiceImportController::class, 'template'])->middleware('permission:service.create');
        Route::post('services/import', [SuperAdminServiceImportController::class, 'analyze'])->middleware('permission:service.create');
        Route::post('services/imports/{bulkImport}/preview', [SuperAdminServiceImportController::class, 'preview'])->middleware('permission:service.create');
        Route::post('services/imports/{bulkImport}/start', [SuperAdminServiceImportController::class, 'start'])->middleware('permission:service.create');
        Route::get('services/imports/{bulkImport}', [SuperAdminServiceImportController::class, 'show'])->middleware('permission:service.create');
        Route::get('services/imports/{bulkImport}/error-report', [SuperAdminServiceImportController::class, 'errorReport'])->middleware('permission:service.create');
        Route::get('services', [SuperAdminServiceController::class, 'index'])->middleware('permission:service.view');
        Route::get('service-groups', function () {
            return response()->json([
                'success' => false,
                'message' => 'Admin API for service groups (list) is not implemented yet.',
            ], 501);
        })->middleware('permission:service.view');
        Route::get('services/{service}', [SuperAdminServiceController::class, 'show'])->middleware('permission:service.view');

        Route::get('dashboard', [DashboardController::class, 'index'])->middleware('permission:dashboard.view');

        Route::get('plan-requests', [PlanRequestController::class, 'index'])->middleware('permission:plan_request.view');
        Route::post('plan-requests', [PlanRequestController::class, 'store'])->middleware('permission:plan_request.create');
        Route::get('plan-requests/{planRequest}', [PlanRequestController::class, 'show'])->middleware('permission:plan_request.view');
        Route::patch('plan-requests/{planRequest}', [PlanRequestController::class, 'update'])->middleware('permission:plan_request.update');
        Route::post('plan-requests/{planRequest}/approve', [PlanRequestController::class, 'approve'])->middleware('permission:plan_request.approve');
        Route::post('plan-requests/{planRequest}/reject', [PlanRequestController::class, 'reject'])->middleware('permission:plan_request.reject');
        Route::delete('plan-requests/{planRequest}', [PlanRequestController::class, 'destroy'])->middleware('permission:plan_request.delete');

        Route::get('coupons', [CouponController::class, 'index'])->middleware('permission:coupon.view');
        Route::post('coupons', [CouponController::class, 'store'])->middleware('permission:coupon.create');
        Route::get('coupons/{coupon}', [CouponController::class, 'show'])->middleware('permission:coupon.view');
        Route::patch('coupons/{coupon}', [CouponController::class, 'update'])->middleware('permission:coupon.update');
        Route::post('coupons/{coupon}/activate', [CouponController::class, 'activate'])->middleware('permission:coupon.update');
        Route::post('coupons/{coupon}/deactivate', [CouponController::class, 'deactivate'])->middleware('permission:coupon.update');
        Route::delete('coupons/{coupon}', [CouponController::class, 'destroy'])->middleware('permission:coupon.delete');
        Route::post('coupons/validate', [CouponController::class, 'validateCoupon'])->middleware('permission:coupon.view');

        Route::get('orders', [OrderController::class, 'index'])->middleware('permission:order.view');
        Route::post('orders', [OrderController::class, 'store'])->middleware('permission:order.create');
        Route::get('orders/{order}', [OrderController::class, 'show'])->middleware('permission:order.view');
        Route::patch('orders/{order}', [OrderController::class, 'update'])->middleware('permission:order.update');
        Route::post('orders/{order}/mark-paid', [OrderController::class, 'markPaid'])->middleware('permission:order.update');
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->middleware('permission:order.cancel');
        Route::delete('orders/{order}', [OrderController::class, 'destroy'])->middleware('permission:order.delete');

        Route::get('email-templates', [EmailTemplateController::class, 'index'])->middleware('permission:email_template.view');
        Route::post('email-templates', [EmailTemplateController::class, 'store'])->middleware('permission:email_template.create');
        Route::get('email-templates/{emailTemplate}', [EmailTemplateController::class, 'show'])->middleware('permission:email_template.view');
        Route::put('email-templates/{emailTemplate}', [EmailTemplateController::class, 'update'])->middleware('permission:email_template.update');
        Route::patch('email-templates/{emailTemplate}', [EmailTemplateController::class, 'update'])->middleware('permission:email_template.update');
        Route::post('email-templates/{emailTemplate}/preview', [EmailTemplateController::class, 'preview'])->middleware('permission:email_template.view');
        Route::post('email-templates/{emailTemplate}/send-test', [EmailTemplateController::class, 'sendTest'])->middleware('permission:email_template.update');
        Route::post('email-templates/{emailTemplate}/activate', [EmailTemplateController::class, 'activate'])->middleware('permission:email_template.update');
        Route::post('email-templates/{emailTemplate}/deactivate', [EmailTemplateController::class, 'deactivate'])->middleware('permission:email_template.update');
        Route::delete('email-templates/{emailTemplate}', [EmailTemplateController::class, 'destroy'])->middleware('permission:email_template.delete');

        Route::get('settings', [SettingController::class, 'index'])->middleware('permission:settings.view');
        Route::patch('settings', [SettingController::class, 'update'])->middleware('permission:settings.update');

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::patch('notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::delete('notifications/{notification}', [NotificationController::class, 'destroy']);
        Route::get('notification-preferences', [NotificationPreferenceController::class, 'show']);
        Route::patch('notification-preferences', [NotificationPreferenceController::class, 'update']);

        Route::get('plans', [SuperAdminSubscriptionPlanController::class, 'index'])->middleware('permission:plan.view');
        Route::post('plans', [SuperAdminSubscriptionPlanController::class, 'store'])->middleware('permission:plan.create');
        Route::get('plans/{subscriptionPlan}', [SuperAdminSubscriptionPlanController::class, 'show'])->middleware('permission:plan.view');
        Route::put('plans/{subscriptionPlan}', [SuperAdminSubscriptionPlanController::class, 'update'])->middleware('permission:plan.update');
        Route::patch('plans/{subscriptionPlan}/status', [SuperAdminSubscriptionPlanController::class, 'toggle'])->middleware('permission:plan.update');
        Route::post('plans/{subscriptionPlan}/addons', [SuperAdminSubscriptionPlanController::class, 'attachAddon'])->middleware('permission:plan.update');
        Route::delete('plans/{subscriptionPlan}/addons/{addon}', [SuperAdminSubscriptionPlanController::class, 'detachAddon'])->middleware('permission:plan.update');
        Route::delete('plans/{subscriptionPlan}', [SuperAdminSubscriptionPlanController::class, 'destroy'])->middleware('permission:plan.delete');

        Route::get('subscriptions', [SuperAdminSubscriptionController::class, 'index'])->middleware('permission:subscription.view');
        Route::get('activity-logs', [SuperAdminActivityLogController::class, 'index'])->middleware('permission:activity_logs.view');
        Route::get('activity-logs/{activityLog}', [SuperAdminActivityLogController::class, 'show'])->middleware('permission:activity_logs.view');
        Route::get('referrals', [SuperAdminReferralController::class, 'index'])->middleware('permission:referral.view');

        Route::prefix('permissions')->group(function (): void {
            Route::get('/', [SuperAdminPermissionController::class, 'index'])->middleware('permission:permission.view');
            Route::get('roles', [SuperAdminPermissionController::class, 'roles'])->middleware('permission:permission.view');
            Route::get('roles/{role}/permissions', [SuperAdminPermissionController::class, 'rolePermissions'])->middleware('permission:permission.view');
            Route::put('roles/{role}/permissions', [SuperAdminPermissionController::class, 'updateRolePermissions'])->middleware('permission:permission.manage');
            Route::get('users', [SuperAdminPermissionController::class, 'users'])->middleware('permission:permission.view');
            Route::get('users/{user}/permissions', [SuperAdminPermissionController::class, 'userPermissions'])->middleware('permission:permission.view');
            Route::put('users/{user}/permissions', [SuperAdminPermissionController::class, 'updateUserPermissions'])->middleware('permission:permission.manage');
            Route::get('me/permissions', [SuperAdminPermissionController::class, 'me'])->middleware('permission:permission.view');
        });
    });
});

Route::prefix('v1')->middleware(['auth:sanctum', 'role:super_admin,super_admin_employee'])->group(function (): void {
    Route::get('super-admin/property-map', [SuperAdminPropertyMapController::class, 'index']);
});
