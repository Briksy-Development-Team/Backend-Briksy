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
