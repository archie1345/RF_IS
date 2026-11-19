<?php

use App\Http\Controllers\Admin\GroupController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'account.active'])
    ->get('/user', fn (Request $request) => $request->user());

// JSON endpoint used by the admin class member modal. The web middleware is
// intentional: the Inertia frontend authenticates with the normal session.
Route::middleware(['web', 'auth', 'account.active', 'verified', 'role:admin,coach'])
    ->get('/admin/groups/{group}/athletes', [GroupController::class, 'athletes'])
    ->name('api.admin.groups.athletes');
