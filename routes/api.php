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
Route::put('/athletes/{id}/restore', [AthleteController::class, 'restore']);

Route::get('/branches', [BranchController::class, 'index']);
Route::get('/branches/{id}', [BranchController::class, 'show']);
Route::post('/branches', [BranchController::class, 'store']);
Route::put('/branches/{id}', [BranchController::class, 'update']);
Route::delete('/branches/{id}', [BranchController::class, 'destroy']);

Route::get('/groups', [GroupController::class, 'index']);
Route::get('/groups/{id}', [GroupController::class, 'show']);
Route::post('/groups', [GroupController::class, 'store']);
Route::put('/groups/{id}', [GroupController::class, 'update']);
Route::delete('/groups/{id}', [GroupController::class, 'destroy']);

Route::get('/coaches', [CoachController::class, 'index']);
Route::get('/coaches/{id}', [CoachController::class, 'show']);
Route::post('/coaches', [CoachController::class, 'store']);
Route::put('/coaches/{id}', [CoachController::class, 'update']);
Route::delete('/coaches/{id}', [CoachController::class, 'destroy']);

Route::get('/payments', [PaymentController::class, 'index']);
Route::get('/payments/{id}', [PaymentController::class, 'show']);
Route::post('/payments', [PaymentController::class, 'store']);
Route::put('/payments/{id}', [PaymentController::class, 'update']);
Route::delete('/payments/{id}', [PaymentController::class, 'destroy']);

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
