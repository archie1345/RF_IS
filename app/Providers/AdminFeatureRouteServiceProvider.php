<?php

namespace App\Providers;

use App\Http\Controllers\AdminFeatureController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AdminFeatureRouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::prefix('admin')->name('admin.')->group(function (): void {
            Route::get('finance-income', [AdminFeatureController::class, 'financeIncome'])->name('finance-income');
            Route::get('finance-output', [AdminFeatureController::class, 'financeOutput'])->name('finance-output');
            Route::post('monthly-dues/generate', [AdminFeatureController::class, 'generateMonthlyDues'])->name('monthly-dues.generate');
            Route::post('schedules/generate-week', [AdminFeatureController::class, 'generateWeeklySessions'])->name('schedules.generate-week');
        });
    }
}
