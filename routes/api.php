<?php

use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\AttemptController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChildDeviceController;
use App\Http\Controllers\Api\ChildMeController;
use App\Http\Controllers\Api\FeedbackController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 — for the future Android/iOS apps (see docs/openapi.yaml)
|--------------------------------------------------------------------------
|
| Every endpoint here calls the exact same services and Policies as the web
| portal (AttemptService, DeviceAuthService, ChildFeedbackService, MfaService)
| — this file only adapts delivery (JSON + bearer token instead of Inertia +
| session cookie), it never reimplements business rules.
|
*/
Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::middleware(['auth:sanctum', 'api.principal:staff'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
    });

    Route::prefix('child')->group(function () {
        Route::post('device/activate', [ChildDeviceController::class, 'activate'])->middleware('throttle:10,1');
        Route::post('device/unlock', [ChildDeviceController::class, 'unlock'])->middleware('throttle:10,1');

        Route::middleware(['auth:sanctum', 'api.principal:child'])->group(function () {
            Route::post('auth/logout', [ChildDeviceController::class, 'logout']);
            Route::get('me', ChildMeController::class);

            Route::get('assignments', [AssignmentController::class, 'index']);
            Route::post('assignments/{assignment}/start', [AssignmentController::class, 'start']);
            Route::get('assignments/{assignment}/feedback', FeedbackController::class);

            Route::get('attempts/{attempt}', [AttemptController::class, 'show']);
            Route::post('attempts/{attempt}/steps/{step}', [AttemptController::class, 'saveStep']);
            Route::post('attempts/{attempt}/submit', [AttemptController::class, 'submit']);
        });
    });
});
