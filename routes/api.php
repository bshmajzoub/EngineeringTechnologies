<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\TaskAssignmentController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskReplyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All routes here are prefixed with /api and use Sanctum token authentication.
| The middleware stack applied per route group:
|   - auth:sanctum   → requires valid Bearer token
|   - active         → rejects deactivated users
|   - role:admin     → admin-only routes
|   - role:employee  → employee-only routes
|
*/

// ─── Public: no auth required ─────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    // 10 attempts per minute per IP. Returns 429 on breach.
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

// ─── Authenticated routes ──────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'active'])->prefix('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/device-token', [AuthController::class, 'registerDeviceToken']);
    Route::delete('/device-token', [AuthController::class, 'removeDeviceToken']);
});

// Task routes shared by admin and employees. Employees only receive authorized own tasks.
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::get('/tasks/{task}', [TaskController::class, 'show']);
    Route::get('/assignments/{assignment}/replies', [TaskReplyController::class, 'index']);

    Route::middleware('role:admin')->group(function () {
        Route::post('/tasks', [TaskController::class, 'store']);
        Route::put('/tasks/{task}', [TaskController::class, 'update']);
        Route::patch('/tasks/{task}', [TaskController::class, 'update']);
        Route::patch('/tasks/{task}/cancel', [TaskController::class, 'cancel']);
        Route::patch('/tasks/{task}/activate', [TaskController::class, 'activate']);
    });

    Route::middleware('role:employee')->prefix('assignments')->group(function () {
        Route::get('/my-current', [TaskAssignmentController::class, 'myCurrent']);
        Route::patch('/{assignment}/accept', [TaskAssignmentController::class, 'accept']);
        Route::patch('/{assignment}/reject', [TaskAssignmentController::class, 'reject']);
        Route::patch('/{assignment}/complete', [TaskAssignmentController::class, 'complete']);
        Route::post('/{assignment}/replies', [TaskReplyController::class, 'store']);
    });
});

// Admin-only employee management routes
Route::middleware(['auth:sanctum', 'active', 'role:admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/tasks', [TaskController::class, 'adminIndex']);
        Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);
        Route::post('/tasks/bulk-delete', [TaskController::class, 'bulkDelete']);

        Route::delete('/employees', [EmployeeController::class, 'deleteAllEmployees']);
        Route::post('/employees/bulk-delete', [EmployeeController::class, 'bulkDelete']);
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy']);
        Route::apiResource('employees', EmployeeController::class)->except(['destroy']);
        Route::patch('/employees/{employee}/toggle-active', [EmployeeController::class, 'toggleActive']);
    });

// Location routes
Route::middleware(['auth:sanctum', 'active'])->prefix('location')->group(function () {
    Route::middleware('role:admin')->group(function () {
        Route::post('/request', [LocationController::class, 'request']);
        Route::get('/live', [LocationController::class, 'live']);
        Route::get('/live/{userId}', [LocationController::class, 'liveForUser']);
    });

    Route::middleware('role:employee')->group(function () {
        Route::post('/sync', [LocationController::class, 'syncBatch']);
        Route::post('/submit', [LocationController::class, 'submit']);
    });
});
