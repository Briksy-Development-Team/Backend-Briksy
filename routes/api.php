<?php

use App\Http\Controllers\Api\Seeker\FavoriteController;
use App\Http\Controllers\Api\Seeker\InquiryController;
use App\Http\Controllers\Api\Seeker\OrganizationSearchController;
use App\Http\Controllers\Api\Seeker\PropertySearchController;
use App\Http\Controllers\Api\Seeker\RegistrationController;
use App\Http\Controllers\Api\Seeker\ReviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/seeker')->group(function (): void {
    Route::post('auth/register', [RegistrationController::class, 'store']);

    Route::get('properties', [PropertySearchController::class, 'index']);
    Route::get('properties/{propertyListing}', [PropertySearchController::class, 'show']);

    Route::get('organizations', [OrganizationSearchController::class, 'index']);
    Route::get('organizations/{organization}', [OrganizationSearchController::class, 'show']);

    Route::post('inquiries', [InquiryController::class, 'store']);
    Route::get('favorites', [FavoriteController::class, 'index']);
    Route::post('favorites', [FavoriteController::class, 'store']);
    Route::delete('favorites/{favorite}', [FavoriteController::class, 'destroy']);
    Route::post('reviews', [ReviewController::class, 'store']);
});
