<?php

use App\Http\Controllers\Admin\AdminAccountLifecycleController;
use App\Http\Controllers\Admin\AdminDataExportController;
use Illuminate\Support\Facades\Route;

Route::get('data-export', [AdminDataExportController::class, 'index'])
    ->name('data-export.index');
Route::get('data-export/download', [AdminDataExportController::class, 'download'])
    ->name('data-export.download');
Route::put('accounts/{user}/status', [AdminAccountLifecycleController::class, 'updateStatus'])
    ->name('accounts.status.update');
