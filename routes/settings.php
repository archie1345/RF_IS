<?php

use App\Http\Controllers\ActiveRoleContextController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'account.active'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('settings/profile/details', [ProfileController::class, 'updateAccountProfile'])->name('profile.details.update');
    Route::post('settings/profile/certifications', [ProfileController::class, 'storeCertification'])->name('profile.certifications.store');
    Route::put('settings/profile/certifications/{certification}', [ProfileController::class, 'updateCertification'])->name('profile.certifications.update');
    Route::delete('settings/profile/certifications/{certification}', [ProfileController::class, 'destroyCertification'])->name('profile.certifications.destroy');
    Route::post('settings/profile/achievements', [ProfileController::class, 'storeAchievement'])->name('profile.achievements.store');
    Route::put('settings/profile/achievements/{achievement}', [ProfileController::class, 'updateAchievement'])->name('profile.achievements.update');
    Route::delete('settings/profile/achievements/{achievement}', [ProfileController::class, 'destroyAchievement'])->name('profile.achievements.destroy');
});

Route::middleware(['auth', 'account.active', 'verified'])->group(function () {
    Route::put('account/active-role', ActiveRoleContextController::class)->name('role-context.update');

    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');
});
