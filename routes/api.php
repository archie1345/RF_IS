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

Route::apiResource('users', UserController::class);
Route::apiResource('athletes', AthleteController::class);
Route::apiResource('branches', BranchController::class);
Route::apiResource('groups', GroupController::class);
Route::apiResource('coaches', CoachController::class);
Route::apiResource('payments', PaymentController::class);
