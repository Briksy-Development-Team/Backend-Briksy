<?php

use App\Http\Controllers\Api\Auth\SocialAuthController;
use App\Http\Controllers\Api\Seeker\FavoriteController;
use App\Http\Controllers\Api\Admin\SeekerController as AdminSeekerController;
use App\Http\Controllers\Api\Admin\OrganizationController as AdminOrganizationController;
use App\Http\Controllers\Api\Admin\StaffController as AdminStaffController;
use App\Http\Controllers\Api\Seeker\InquiryController;
use App\Http\Controllers\Api\Seeker\OrganizationSearchController;
use App\Http\Controllers\Api\Seeker\PropertySearchController;
use App\Http\Controllers\Api\Seeker\RegistrationController;
use App\Http\Controllers\Api\Seeker\ReviewController;
use App\Http\Controllers\Api\Seeker\SeekerProfileController;
use App\Http\Controllers\Api\Seeker\SeekerSavedSearchController;
use App\Support\Auth\AdminAbilities;
use App\Http\Controllers\Api\SuperAdmin\OrganizationController;
use App\Http\Controllers\Api\SuperAdmin\OrganizationTypeController;
use App\Http\Controllers\Api\SuperAdmin\SeekerController as SuperAdminSeekerController;
use App\Http\Controllers\Api\SuperAdmin\StaffController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('auth')->group(function (): void {
    Route::post('social/{provider}', [SocialAuthController::class, 'login']);
});

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

Route::prefix('admin')->group(function (): void {
    Route::post('auth/register', [RegistrationController::class, 'registerAdmin']);
    Route::post('auth/login', [RegistrationController::class, 'loginAdmin']);

    Route::middleware(['auth:sanctum', AdminAbilities::middleware(AdminAbilities::ADMIN_AND_STAFF)])->group(function (): void {
        Route::get('auth/me', [RegistrationController::class, 'me']);
        Route::post('auth/logout', [RegistrationController::class, 'logoutSeeker']);

        Route::get('seekers', [AdminSeekerController::class, 'index']);
        Route::get('seekers/{user}', [AdminSeekerController::class, 'show']);

        Route::get('businesses', [AdminOrganizationController::class, 'index']);
        Route::get('businesses/{organization}', [AdminOrganizationController::class, 'show']);

        Route::prefix('properties')->group(function (): void {
            Route::get('/', function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin API for properties (list) is not implemented yet.',
                ], 501);
            });
            Route::get('{propertyListing}', function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin API for properties (show) is not implemented yet.',
                ], 501);
            });
        });

        Route::prefix('services')->group(function (): void {
            Route::get('groups', function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin API for service groups (list) is not implemented yet.',
                ], 501);
            });
            Route::get('groups/{serviceGroup}', function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin API for service groups (show) is not implemented yet.',
                ], 501);
            });
            Route::get('/', function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin API for services (list) is not implemented yet.',
                ], 501);
            });
            Route::get('{service}', function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin API for services (show) is not implemented yet.',
                ], 501);
            });
        });
    });

    Route::middleware(['auth:sanctum', AdminAbilities::middleware(AdminAbilities::ADMIN_ONLY)])->group(function (): void {
        Route::post('auth/register-staff', [RegistrationController::class, 'registerAdminStaff']);

        Route::get('staff', [AdminStaffController::class, 'index']);
        Route::post('staff', [AdminStaffController::class, 'store']);
        Route::get('staff/{user}', [AdminStaffController::class, 'show']);
        Route::put('staff/{user}', [AdminStaffController::class, 'update']);
        Route::delete('staff/{user}', [AdminStaffController::class, 'destroy']);

        Route::prefix('subscriptions')->group(function (): void {
            Route::get('plans', function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin API for subscription plans (list) is not implemented yet.',
                ], 501);
            });
            Route::post('plans', function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin API for subscription plans (create) is not implemented yet.',
                ], 501);
            });
            Route::get('plans/{subscriptionPlan}', function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin API for subscription plans (show) is not implemented yet.',
                ], 501);
            });
            Route::put('plans/{subscriptionPlan}', function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin API for subscription plans (update) is not implemented yet.',
                ], 501);
            });
            Route::delete('plans/{subscriptionPlan}', function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin API for subscription plans (delete) is not implemented yet.',
                ], 501);
            });
        });
    });
});

Route::prefix('super-admin')->group(function (): void {
    Route::post('auth/register', [RegistrationController::class, 'registerSuperAdmin']);
    Route::post('auth/login', [RegistrationController::class, 'loginSuperAdmin']);

    Route::middleware(['auth:sanctum', 'abilities:super_admin'])->group(function (): void {
        Route::get('seekers', [SuperAdminSeekerController::class, 'index']);
        Route::post('seekers', [SuperAdminSeekerController::class, 'store']);
        Route::get('seekers/{seeker}', [SuperAdminSeekerController::class, 'show']);
        Route::put('seekers/{seeker}', [SuperAdminSeekerController::class, 'update']);

        Route::get('staff', [StaffController::class, 'index']);
        Route::post('staff', [StaffController::class, 'store']);
        Route::get('staff/{staff}', [StaffController::class, 'show']);
        Route::put('staff/{staff}', [StaffController::class, 'update']);

        Route::get('organizations', [OrganizationController::class, 'index']);
        Route::post('organizations', [OrganizationController::class, 'store']);
        Route::get('organizations/{organization}', [OrganizationController::class, 'show']);
        Route::put('organizations/{organization}', [OrganizationController::class, 'update']);

        Route::get('organization-types', [OrganizationTypeController::class, 'index']);
        Route::post('organization-types', [OrganizationTypeController::class, 'store']);
        Route::put('organization-types/{organizationType}', [OrganizationTypeController::class, 'update']);
    });
});
