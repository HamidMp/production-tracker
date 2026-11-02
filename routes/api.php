<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\JobsPerStatusReportController;
use App\Http\Controllers\JobStatusController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderJobController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => new UserResource($request->user()));

    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/jobs', [OrderJobController::class, 'store']);

    Route::get('/jobs', [OrderJobController::class, 'index']);
    Route::get('/jobs/{job}', [OrderJobController::class, 'show']);
    Route::get('/jobs/{job}/history', [JobStatusController::class, 'index']);
    Route::patch('/jobs/{job}/status', [JobStatusController::class, 'update']);

    Route::get('/reports/jobs-per-status', JobsPerStatusReportController::class);
});
