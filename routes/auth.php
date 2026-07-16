<?php

use App\Http\Controllers\Auth\InvitationController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
    Route::post('invitations/{token}', [InvitationController::class, 'store'])->name('invitations.store');
});
