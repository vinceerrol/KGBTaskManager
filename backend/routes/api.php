<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\RecurringTaskController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskDraftController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/auth/demo-login', [AuthController::class, 'demoLogin'])->middleware('throttle:login');

// Signed, expiring links are issued with each attachment, so no bearer token is needed to open one.
Route::get('/attachments/{attachment}/download', [TaskController::class, 'downloadAttachment'])
    ->middleware('signed')
    ->name('attachments.download');

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth & Session
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword'])->middleware('throttle:login');

    // Dashboard (CEO & Management Overview)
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Tasks
    Route::get('/task-drafts/context', [TaskDraftController::class, 'context']);
    Route::post('/task-drafts/interpret', [TaskDraftController::class, 'interpret'])->middleware('throttle:20,1');
    Route::get('/tasks/my-tasks', [TaskController::class, 'myTasks']);
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::get('/tasks/{id}', [TaskController::class, 'show']);
    Route::put('/tasks/{id}', [TaskController::class, 'update']);
    Route::delete('/tasks/{id}', [TaskController::class, 'destroy']);
    Route::post('/tasks/{id}/start', [TaskController::class, 'start']);
    Route::post('/tasks/{id}/complete', [TaskController::class, 'complete']);
    Route::post('/tasks/{id}/reopen', [TaskController::class, 'reopen']);
    Route::post('/tasks/{id}/attachments', [TaskController::class, 'uploadAttachment']);
    Route::delete('/attachments/{id}', [TaskController::class, 'deleteAttachment']);
    Route::post('/tasks/{id}/comments', [TaskController::class, 'addComment']);

    // Teams
    Route::get('/teams', [TeamController::class, 'index']);
    Route::get('/teams/{id}', [TeamController::class, 'show']);
    Route::post('/teams', [TeamController::class, 'store']);
    Route::put('/teams/{id}', [TeamController::class, 'update']);
    Route::delete('/teams/{id}', [TeamController::class, 'destroy']);

    // Users
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // Templates
    Route::get('/templates', [TemplateController::class, 'index']);
    Route::post('/templates', [TemplateController::class, 'store']);
    Route::put('/templates/{id}', [TemplateController::class, 'update']);
    Route::delete('/templates/{id}', [TemplateController::class, 'destroy']);

    // Recurring Tasks
    Route::get('/recurring-tasks', [RecurringTaskController::class, 'index']);
    Route::post('/recurring-tasks', [RecurringTaskController::class, 'store']);
    Route::post('/recurring-tasks/{id}/toggle', [RecurringTaskController::class, 'toggle']);
    Route::put('/recurring-tasks/{id}', [RecurringTaskController::class, 'update']);
    Route::delete('/recurring-tasks/{id}', [RecurringTaskController::class, 'destroy']);
});
