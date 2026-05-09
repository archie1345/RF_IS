<?php

use App\Http\Controllers\AdminManagementController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AthleteManagementController;
use App\Http\Controllers\AttendanceManagementController;
use App\Http\Controllers\ChampionshipManagementController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentManagementController;
use App\Http\Controllers\ParentChildContextController;
use App\Http\Controllers\SessionManagementController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('components-playground', function () {
        return Inertia::render('ComponentsPlaygroundPage');
    })->name('components-playground');

    Route::get('athletes', [AthleteManagementController::class, 'index'])->name('athletes.index');
    Route::get('athletes/{athlete}', [AthleteManagementController::class, 'show'])->name('athletes.show');
    Route::post('athletes', [AthleteManagementController::class, 'store'])->name('athletes.store');
    Route::put('athletes/{athlete}', [AthleteManagementController::class, 'update'])->name('athletes.update');
    Route::delete('athletes/{athlete}', [AthleteManagementController::class, 'destroy'])->name('athletes.destroy');
    Route::post('athletes/{athlete}/parent-link', [AthleteManagementController::class, 'linkParent'])->name('athletes.parent-link');

    Route::get('admin', [AdminManagementController::class, 'index'])->name('admin.index');
    Route::get('admin/activity-logs', [ActivityLogController::class, 'index'])->name('admin.activity-logs.index');
    Route::post('admin/accounts', [AdminManagementController::class, 'store'])->name('admin.accounts.store');
    Route::put('admin/accounts/{user}', [AdminManagementController::class, 'update'])->name('admin.accounts.update');
    Route::post('admin/branches', [AdminManagementController::class, 'storeBranch'])->name('admin.branches.store');
    Route::put('admin/branches/{branch}', [AdminManagementController::class, 'updateBranch'])->name('admin.branches.update');
    Route::delete('admin/branches/{branch}', [AdminManagementController::class, 'destroyBranch'])->name('admin.branches.destroy');
    Route::post('admin/groups', [AdminManagementController::class, 'storeGroup'])->name('admin.groups.store');
    Route::put('admin/groups/{group}', [AdminManagementController::class, 'updateGroup'])->name('admin.groups.update');
    Route::delete('admin/groups/{group}', [AdminManagementController::class, 'destroyGroup'])->name('admin.groups.destroy');
    Route::post('admin/data-transfer/import', [AdminManagementController::class, 'importCsv'])->name('admin.data-transfer.import');
    Route::get('admin/data-transfer/export', [AdminManagementController::class, 'exportCsv'])->name('admin.data-transfer.export');
    Route::get('admin/data-transfer/template', [AdminManagementController::class, 'downloadTemplate'])->name('admin.data-transfer.template');

    Route::get('payments', [PaymentManagementController::class, 'index'])->name('payments.index');
    Route::post('payments', [PaymentManagementController::class, 'store'])->name('payments.store');

    Route::get('attendance', [AttendanceManagementController::class, 'index'])->name('attendance.index');
    Route::post('attendance', [AttendanceManagementController::class, 'store'])->name('attendance.store');
    Route::post('attendance/bulk-update', [AttendanceManagementController::class, 'bulkUpdate'])->name('attendance.bulk-update');
    Route::put('attendance/{attendance}', [AttendanceManagementController::class, 'update'])->name('attendance.update');

    Route::get('championships', [ChampionshipManagementController::class, 'index'])->name('championships.index');
    Route::post('championships/registrations', [ChampionshipManagementController::class, 'storeRegistration'])->name('championships.registrations.store');

    Route::get('sessions', [SessionManagementController::class, 'index'])->name('sessions.index');
    Route::post('sessions', [SessionManagementController::class, 'store'])->name('sessions.store');
    Route::put('sessions/{session}', [SessionManagementController::class, 'update'])->name('sessions.update');
    Route::delete('sessions/{session}', [SessionManagementController::class, 'destroy'])->name('sessions.destroy');
    Route::post('sessions/{session}/join', [SessionManagementController::class, 'join'])->name('sessions.join');
    Route::get('sessions/{session}/attendance', [SessionManagementController::class, 'attendanceSheet'])->name('sessions.attendance');
    Route::post('sessions/{session}/coach-attendance', [SessionManagementController::class, 'addCoachAttendance'])->name('sessions.coach-attendance.store');
    Route::put('sessions/coach-attendance/{coachAttendance}', [SessionManagementController::class, 'updateCoachAttendance'])->name('sessions.coach-attendance.update');
    Route::delete('sessions/coach-attendance/{coachAttendance}', [SessionManagementController::class, 'destroyCoachAttendance'])->name('sessions.coach-attendance.destroy');

    Route::get('parent/children', [ParentChildContextController::class, 'index'])->name('parent.children.index');
    Route::post('parent/children/{athlete}/switch', [ParentChildContextController::class, 'switch'])->name('parent.children.switch');
    Route::delete('parent/children/switch', [ParentChildContextController::class, 'clear'])->name('parent.children.clear');
});

require __DIR__.'/settings.php';
