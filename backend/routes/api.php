<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AssigneeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/meetings', [MeetingController::class, 'index']);
    Route::post('/meetings/upload', [MeetingController::class, 'upload']);
    Route::get('/meetings/{meeting}', [MeetingController::class, 'show']);
    Route::post('/meetings/{meeting}/reprocess', [MeetingController::class, 'reprocess'])->middleware('role:admin,manager');

    Route::get('/assignees', [AssigneeController::class, 'index'])->middleware('role:admin,manager');
    Route::post('/assignees', [AssigneeController::class, 'store'])->middleware('role:admin,manager');

    Route::get('/tasks', [TaskController::class, 'index']);
    Route::get('/tasks/{task}', [TaskController::class, 'show']);
    Route::post('/tasks/{task}/auto-assign', [TaskController::class, 'autoAssign'])->middleware('role:admin,manager');
    Route::put('/tasks/{task}/assignee', [TaskController::class, 'updateAssignee'])->middleware('role:admin,manager');
    Route::post('/tasks/{task}/send-email', [TaskController::class, 'sendEmail'])->middleware('role:admin,manager');

    Route::post('/tasks/{task}/approve', [ApprovalController::class, 'approve'])->middleware('role:admin,manager');
    Route::post('/tasks/{task}/reject', [ApprovalController::class, 'reject'])->middleware('role:admin,manager');
});
