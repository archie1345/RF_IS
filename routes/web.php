<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('athletes', function () {
        return Inertia::render('Athletes/Index');
    })->name('athletes.index');

    Route::get('payments', function () {
        return Inertia::render('Payments/Index');
    })->name('payments.index');

    Route::get('attendance', function () {
        return Inertia::render('Attendance/Index');
    })->name('attendance.index');

    Route::get('championships', function () {
        return Inertia::render('Championships/Index');
    })->name('championships.index');

    Route::get('sessions', function () {
        return Inertia::render('Sessions/Index');
    })->name('sessions.index');
});

require __DIR__.'/settings.php';
