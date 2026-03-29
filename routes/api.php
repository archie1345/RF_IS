<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AthleteController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\CoachController;
use App\Http\Controllers\Api\PaymentController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/athletes', [AthleteController::class, 'index']);
Route::get('/athletes/{id}', [AthleteController::class, 'show']);
Route::post('/athletes', [AthleteController::class, 'store']);
Route::put('/athletes/{id}', [AthleteController::class, 'update']);
Route::delete('/athletes/{id}', [AthleteController::class, 'destroy']);
Route::get('/users', [UserController::class, 'index']);
Route::get('/users/{id}', [UserController::class, 'show']);
Route::post('/users', [UserController::class, 'store']);
Route::put('/users/{id}', [UserController::class, 'update']);
Route::delete('/users/{id}', [UserController::class, 'destroy']);

// Route::apiResource('users', UserController::class);
// Route::apiResource('athletes', AthleteController::class);
// Route::apiResource('branches', BranchController::class);
// Route::apiResource('groups', GroupController::class);
// Route::apiResource('coaches', CoachController::class);
// Route::apiResource('payments', PaymentController::class);
