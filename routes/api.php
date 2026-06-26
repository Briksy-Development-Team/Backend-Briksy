<?php

use App\Http\Controllers\Api\Auth\SocialAuthController;
use App\Http\Controllers\Api\Seeker\FavoriteController;
use App\Http\Controllers\Api\Admin\SeekerController as AdminSeekerController;
use App\Http\Controllers\Api\Admin\OrganizationController as AdminOrganizationController;
use App\Http\Controllers\Api\Admin\StaffController as AdminStaffController;
use App\Http\Controllers\Api\Admin\PlanRequestController as AdminPlanRequestController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\Api\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Api\Admin\CouponController as AdminCouponController;
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
use App\Http\Controllers\Api\SuperAdmin\SettingController;
use App\Http\Controllers\Api\SuperAdmin\PermissionController as SuperAdminPermissionController;
use App\Http\Controllers\Api\SuperAdmin\PropertyController as SuperAdminPropertyController;
use App\Http\Controllers\Api\SuperAdmin\SubscriptionPlanController as SuperAdminSubscriptionPlanController;
use App\Http\Controllers\Api\SuperAdmin\SeekerController as SuperAdminSeekerController;
use App\Http\Controllers\Api\SuperAdmin\StaffController;
use App\Http\Controllers\Api\Admin\PropertyController as AdminPropertyController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\NotificationPreferenceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->get('me/permissions', [SuperAdminPermissionController::class, 'me']);

Route::prefix('auth')->group(function (): void {
    Route::post('social/{provider}', [SocialAuthController::class, 'login']);
});

Route::get('settings/public', [SettingController::class, 'publicSettings']);

Route::prefix('seeker')->group(function (): void {
    Route::post('auth/register', [RegistrationController::class, 'store']);
    Route::post('auth/login', [RegistrationController::class, 'loginSeeker']);

    Route::get('properties', [PropertySearchController::class, 'index']);
    Route::get('properties/{propertyListing}', [PropertySearchController::class, 'show']);

    Route::get('organizations', [OrganizationSearchController::class, 'index']);
    Route::get('organizations/{organization}', [OrganizationSearchController::class, 'show']);

    Route::post('inquiries', [InquiryController::class, 'store']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('favorites', [FavoriteController::class, 'index']);
        Route::post('favorites', [FavoriteController::class, 'store']);
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

Route::prefix('v1')->group(function (): void {
    Route::prefix('seeker')->group(function (): void {
        Route::post('auth/register', [RegistrationController::class, 'store']);
        Route::post('auth/login', [RegistrationController::class, 'loginSeeker']);

        Route::get('properties', [PropertySearchController::class, 'index']);
        Route::get('properties/{propertyListing}', [PropertySearchController::class, 'show']);

        Route::get('organizations', [OrganizationSearchController::class, 'index']);
        Route::get('organizations/{organization}', [OrganizationSearchController::class, 'show']);

        Route::post('inquiries', [InquiryController::class, 'store']);

        Route::get('favorites', [FavoriteController::class, 'index']);
        Route::post('favorites', [FavoriteController::class, 'store']);
        Route::delete('favorites/{favorite}', [FavoriteController::class, 'destroy']);

        Route::get('reviews', [ReviewController::class, 'index']);
        Route::post('reviews', [ReviewController::class, 'store']);

        Route::middleware('auth:sanctum')->group(function (): void {
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
});

Route::prefix('admin')->group(function (): void {
    Route::post('auth/register', [RegistrationController::class, 'registerAdmin']);
    Route::post('auth/login', [RegistrationController::class, 'loginAdmin']);

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

        Route::post('coupons/validate', [AdminCouponController::class, 'validateCoupon'])->middleware('permission:coupon.view');

        Route::get('seekers', [AdminSeekerController::class, 'index'])->middleware('permission:user.view');
        Route::get('seekers/{user}', [AdminSeekerController::class, 'show'])->middleware('permission:user.view');

        Route::get('businesses', [AdminOrganizationController::class, 'index'])->middleware('permission:company.view');
        Route::get('businesses/{organization}', [AdminOrganizationController::class, 'show'])->middleware('permission:company.view');
        Route::put('businesses/{organization}', [AdminOrganizationController::class, 'update'])->middleware('permission:company.update');

        Route::get('properties', [AdminPropertyController::class, 'index'])->middleware(['module:property_management', 'permission:property.view']);
        Route::get('properties/map', [AdminPropertyController::class, 'map'])->middleware(['module:property_management', 'permission:property.view']);
        Route::post('properties', [AdminPropertyController::class, 'store'])->middleware(['module:property_management', 'permission:property.create']);
        Route::get('properties/{propertyListing}', [AdminPropertyController::class, 'show'])->middleware(['module:property_management', 'permission:property.view']);
        Route::put('properties/{propertyListing}', [AdminPropertyController::class, 'update'])->middleware(['module:property_management', 'permission:property.update']);
        Route::delete('properties/{propertyListing}', [AdminPropertyController::class, 'destroy'])->middleware(['module:property_management', 'permission:property.delete']);

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::patch('notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::delete('notifications/{notification}', [NotificationController::class, 'destroy']);
        Route::get('notification-preferences', [NotificationPreferenceController::class, 'show']);
        Route::patch('notification-preferences', [NotificationPreferenceController::class, 'update']);

        Route::get('services', [SuperAdminServiceController::class, 'index'])->middleware(['module:service_management', 'permission:service.view']);
        Route::post('services', [SuperAdminServiceController::class, 'store'])->middleware(['module:service_management', 'permission:service.create']);
        Route::get('services/{service}', [SuperAdminServiceController::class, 'show'])->middleware(['module:service_management', 'permission:service.view']);
        Route::put('services/{service}', [SuperAdminServiceController::class, 'update'])->middleware(['module:service_management', 'permission:service.update']);
        Route::delete('services/{service}', [SuperAdminServiceController::class, 'destroy'])->middleware(['module:service_management', 'permission:service.delete']);
    });

    Route::middleware(['auth:sanctum', 'role:admin'])->group(function (): void {
        Route::post('auth/register-staff', [RegistrationController::class, 'registerAdminStaff'])
            ->withoutMiddleware('role:admin');

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

    Route::middleware(['auth:sanctum', 'role:super_admin'])->group(function (): void {
        Route::get('auth/me', [RegistrationController::class, 'me']);
        Route::post('auth/logout', [RegistrationController::class, 'logoutSeeker']);

        Route::get('seekers', [SuperAdminSeekerController::class, 'index'])->middleware('permission:user.view');
        Route::post('seekers', [SuperAdminSeekerController::class, 'store'])->middleware('permission:user.create');
        Route::get('seekers/{seeker}', [SuperAdminSeekerController::class, 'show'])->middleware('permission:user.view');
        Route::put('seekers/{seeker}', [SuperAdminSeekerController::class, 'update'])->middleware('permission:user.update');
        Route::delete('seekers/{seeker}', [SuperAdminSeekerController::class, 'destroy'])->middleware('permission:user.delete');

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
        Route::patch('email-templates/{emailTemplate}', [EmailTemplateController::class, 'update'])->middleware('permission:email_template.update');
        Route::post('email-templates/{emailTemplate}/preview', [EmailTemplateController::class, 'preview'])->middleware('permission:email_template.view');
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
        Route::delete('plans/{subscriptionPlan}', [SuperAdminSubscriptionPlanController::class, 'destroy'])->middleware('permission:plan.delete');

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
