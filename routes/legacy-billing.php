<?php

use App\Http\Controllers\Admin\BillingSettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Legacy billing action aliases
|--------------------------------------------------------------------------
|
| AdminFeaturePage still imports the historical admin.monthly-dues action
| namespace. Keep these aliases thin: all behavior remains in the current
| BillingSettingsController and the primary UI remains /admin/billing-settings.
|
*/

Route::post('monthly-dues/generate', [BillingSettingsController::class, 'generateMonthly'])
    ->name('monthly-dues.generate');

Route::post('monthly-dues/settings', [BillingSettingsController::class, 'updateSchedule'])
    ->name('monthly-dues.settings');
