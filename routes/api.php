<?php

use App\Http\Controllers\Api\AthleteController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CoachController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'account.active'])->group(function (): void {
    Route::get('/user', fn (Request $request) => $request->user());

    Route::middleware('role:admin')->group(function (): void {
        Route::apiResource('athletes', AthleteController::class)->except('create', 'edit');
        Route::put('athletes/{athlete}/restore', [AthleteController::class, 'restore'])->name('athletes.restore');

        Route::apiResource('branches', BranchController::class)->except('create', 'edit');
        Route::apiResource('groups', GroupController::class)->except('create', 'edit');
        Route::apiResource('coaches', CoachController::class)->except('create', 'edit');
        Route::apiResource('payments', PaymentController::class)->except('create', 'edit');
        Route::apiResource('users', UserController::class)->except('create', 'edit');
    });
});
