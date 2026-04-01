<?php

use App\Http\Controllers\Api\Auth\SocialAuthController;
use App\Http\Controllers\Api\Seeker\FavoriteController;
use App\Http\Controllers\Api\Seeker\InquiryController;
use App\Http\Controllers\Api\Seeker\OrganizationSearchController;
use App\Http\Controllers\Api\Seeker\PropertySearchController;
use App\Http\Controllers\Api\Seeker\RegistrationController;
use App\Http\Controllers\Api\Seeker\ReviewController;
use App\Http\Controllers\Api\Seeker\SeekerProfileController;
use App\Http\Controllers\Api\Seeker\SeekerSavedSearchController;
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

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/register-staff', [RegistrationController::class, 'registerAdminStaff']);
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
