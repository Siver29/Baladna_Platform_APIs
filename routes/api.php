<?php

use App\Http\Controllers\Api\V1\Admin\AdminAgencyController;
use App\Http\Controllers\Api\V1\Admin\AdminAreaController;
use App\Http\Controllers\Api\V1\Admin\AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\AdminReportController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\AgencyController;
use App\Http\Controllers\Api\V1\AreaController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\EmployeeReportController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\WebsiteController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::patch('/me', [AuthController::class, 'updateProfile']);
    });

    /*
    |--------------------------------------------------------------------------
    | Public Reference Data
    |--------------------------------------------------------------------------
    */

    Route::get('/areas', [AreaController::class, 'index']);
    Route::get('/areas/{area}', [AreaController::class, 'show']);

    Route::get('/agencies', [AgencyController::class, 'index']);
    Route::get('/agencies/{agency}', [AgencyController::class, 'show']);

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);

    /*
    |--------------------------------------------------------------------------
| Anonymous Report Submission (Public)
    |--------------------------------------------------------------------------
    */

    // Registered before the auth'd report routes so it does not collide with {report}.
    Route::post('/reports/anonymous', [ReportController::class, 'storeAnonymous']);

    /*
    |--------------------------------------------------------------------------
    | Website Landing Page (Public - no auth)
    |--------------------------------------------------------------------------
    */

    Route::get('/website/latest-anonymous-reports', [WebsiteController::class, 'latestAnonymousReports']);
    Route::get('/website/stats', [WebsiteController::class, 'stats']);

    /*
    |--------------------------------------------------------------------------
    | Citizen Reports
    |--------------------------------------------------------------------------
    */

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/reports', [ReportController::class, 'index']);
        Route::post('/reports', [ReportController::class, 'store']);
        Route::get('/reports/{report}', [ReportController::class, 'show']);
        Route::patch('/reports/{report}', [ReportController::class, 'update']);
        Route::post('/reports/{report}/cancel', [ReportController::class, 'cancel']);

        Route::get('/my-reports', [ReportController::class, 'myReports']);

        Route::post('/reports/{report}/images', [ReportController::class, 'storeImages']);
        Route::delete('/reports/{report}/images/{image}', [ReportController::class, 'destroyImage']);

        Route::post('/reports/{report}/confirm', [ReportController::class, 'confirm']);
        Route::delete('/reports/{report}/confirm', [ReportController::class, 'unconfirm']);

        Route::get('/reports/{report}/history', [ReportController::class, 'history']);

        Route::post('/reports/{report}/review', [ReportController::class, 'review']);
    });

    /*
    |--------------------------------------------------------------------------
    | Employee Reports
    |--------------------------------------------------------------------------
    */

    Route::middleware(['auth:sanctum', 'employee'])->prefix('employee')->group(function () {
        Route::get('/reports', [EmployeeReportController::class, 'index']);
        Route::get('/reports/{report}', [EmployeeReportController::class, 'show']);
        Route::patch('/reports/{report}/status', [EmployeeReportController::class, 'updateStatus']);
        Route::post('/reports/{report}/public-note', [EmployeeReportController::class, 'addPublicNote']);
    });

    /*
    |--------------------------------------------------------------------------
    | Community
    |--------------------------------------------------------------------------
    */

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/posts', [PostController::class, 'index']);
        Route::post('/posts', [PostController::class, 'store']);
        Route::get('/posts/{post}', [PostController::class, 'show']);
        Route::patch('/posts/{post}', [PostController::class, 'update']);
        Route::delete('/posts/{post}', [PostController::class, 'destroy']);

        Route::get('/posts/{post}/comments', [CommentController::class, 'index']);
        Route::post('/posts/{post}/comments', [CommentController::class, 'store']);
        Route::patch('/comments/{comment}', [CommentController::class, 'update']);
        Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | Admin CRUD
    |--------------------------------------------------------------------------
    */

    Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
        Route::apiResource('areas', AdminAreaController::class);
        Route::apiResource('agencies', AdminAgencyController::class);
        Route::apiResource('categories', AdminCategoryController::class);
        Route::apiResource('users', AdminUserController::class);

        Route::patch('/reports/{report}/assign', [AdminReportController::class, 'assign']);
    });
});
