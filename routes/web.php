<?php

use App\Http\Controllers\AdminManagementController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\UsersManagementController;
use App\Http\Controllers\AttendanceManagementController;
use App\Http\Controllers\ChampionshipManagementController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentManagementController;
use App\Http\Controllers\ParentChildContextController;
use App\Http\Controllers\ProfileAccessController;
use App\Http\Controllers\SessionManagementController;
use App\Http\Controllers\UserAchievementController;
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
    Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::get('components-playground', function () {
        return Inertia::render('ComponentsPlaygroundPage');
    })->name('components-playground');

    Route::get('users', [ProfileAccessController::class, 'usersIndex'])->name('athletes.index');
    Route::get('users/user/{user}', [UsersManagementController::class, 'showByUser'])->name('athletes.show-by-user');
    Route::put('users/user/{user}', [UsersManagementController::class, 'upsertByUser'])->name('athletes.upsert-by-user');
    Route::get('users/{user}', [ProfileAccessController::class, 'show'])->name('athletes.show');
    Route::patch('users/{user}/account', [ProfileAccessController::class, 'updateAccount'])->name('users.account.update');
    Route::post('users/{user}/profile', [ProfileAccessController::class, 'updateAccountProfile'])->name('users.profile.update');
    Route::put('users/{user}/athlete-profile', [ProfileAccessController::class, 'updateAthleteProfile'])->name('users.athlete-profile.update');
    Route::put('users/{user}/coach-profile', [AdminManagementController::class, 'updateCoachProfile'])->name('users.coach-profile.update');
    Route::put('users/{user}/parent-profile', [AdminManagementController::class, 'updateParentProfile'])->name('users.parent-profile.update');
    Route::put('users/parents/{parent}/children', [UsersManagementController::class, 'syncParentChildren'])->name('parents.children.sync');
    Route::post('users/{user}/certifications', [ProfileAccessController::class, 'storeUserCertification'])->name('users.certifications.store');
    Route::put('users/{user}/certifications/{certification}', [ProfileAccessController::class, 'updateUserCertification'])->name('users.certifications.update');
    Route::post('users/{user}/achievements', [ProfileAccessController::class, 'storeUserAchievement'])->name('users.achievements.store');
    Route::put('users/{user}/achievements/{achievement}', [ProfileAccessController::class, 'updateUserAchievement'])->name('users.achievements.update');
    Route::post('users', [UsersManagementController::class, 'store'])->name('athletes.store');
    Route::put('users/{athlete}', [UsersManagementController::class, 'update'])->name('athletes.update');
    Route::delete('users/{athlete}', [UsersManagementController::class, 'destroy'])->name('athletes.destroy');
    Route::post('users/{athlete}/parent-link', [UsersManagementController::class, 'linkParent'])->name('athletes.parent-link');

    Route::get('admin', [AdminManagementController::class, 'index'])->name('admin.index');
    Route::get('admin/activity-logs', [ActivityLogController::class, 'index'])->name('admin.activity-logs.index');
    Route::post('admin/accounts', [AdminManagementController::class, 'store'])->name('admin.accounts.store');
    Route::get('admin/accounts/{user}', [AdminManagementController::class, 'show'])->name('admin.accounts.show');
    Route::put('admin/accounts/{user}', [AdminManagementController::class, 'update'])->name('admin.accounts.update');
    Route::post('admin/accounts/{user}/profile', [AdminManagementController::class, 'updateAccountProfile'])->name('admin.accounts.profile.update');
    Route::delete('admin/accounts/{user}', [AdminManagementController::class, 'destroyAccount'])->name('admin.accounts.destroy');
    Route::put('admin/accounts/{id}/restore', [AdminManagementController::class, 'restoreAccount'])->name('admin.accounts.restore');
    Route::post('admin/branches', [AdminManagementController::class, 'storeBranch'])->name('admin.branches.store');
    Route::put('admin/branches/{branch}', [AdminManagementController::class, 'updateBranch'])->name('admin.branches.update');
    Route::delete('admin/branches/{branch}', [AdminManagementController::class, 'destroyBranch'])->name('admin.branches.destroy');
    Route::post('admin/groups', [AdminManagementController::class, 'storeGroup'])->name('admin.groups.store');
    Route::put('admin/groups/{group}', [AdminManagementController::class, 'updateGroup'])->name('admin.groups.update');
    Route::delete('admin/groups/{group}', [AdminManagementController::class, 'destroyGroup'])->name('admin.groups.destroy');
    Route::post('admin/data-transfer/import', [AdminManagementController::class, 'importCsv'])->name('admin.data-transfer.import');
    Route::get('admin/data-transfer/export', [AdminManagementController::class, 'exportCsv'])->name('admin.data-transfer.export');
    Route::get('admin/data-transfer/template', [AdminManagementController::class, 'downloadTemplate'])->name('admin.data-transfer.template');
    Route::post('admin/invoice-template', [AdminManagementController::class, 'updateInvoiceTemplate'])->name('admin.invoice-template.update');
    Route::redirect('coach-parent-management', '/users')->name('coach-parent.index');

    Route::get('payments', [PaymentManagementController::class, 'index'])->name('payments.index');
    Route::post('payments', [PaymentManagementController::class, 'store'])->name('payments.store');
    Route::put('payments/{payment}', [PaymentManagementController::class, 'update'])->name('payments.update');
    Route::delete('payments/{payment}', [PaymentManagementController::class, 'destroy'])->name('payments.destroy');
    Route::put('payments/{payment}/status', [PaymentManagementController::class, 'updateStatus'])->name('payments.status.update');
    Route::post('payments/{payment}/proof', [PaymentManagementController::class, 'submitProof'])->name('payments.proof.submit');
    Route::put('payments/{payment}/proof-review', [PaymentManagementController::class, 'reviewProof'])->name('payments.proof.review');
    Route::get('payments/{payment}/export', [PaymentManagementController::class, 'exportInvoice'])->name('payments.export');

    Route::get('attendance', [AttendanceManagementController::class, 'index'])->name('attendance.index');
    Route::post('attendance', [AttendanceManagementController::class, 'store'])->name('attendance.store');
    Route::post('attendance/bulk-update', [AttendanceManagementController::class, 'bulkUpdate'])->name('attendance.bulk-update');
    Route::put('attendance/{attendance}', [AttendanceManagementController::class, 'update'])->name('attendance.update');

    Route::get('championships', [ChampionshipManagementController::class, 'index'])->name('championships.index');
    Route::get('championships/{event}', [ChampionshipManagementController::class, 'show'])->name('championships.show');
    Route::post('championships/events', [ChampionshipManagementController::class, 'storeEvent'])->name('championships.events.store');
    Route::post('championships/registrations', [ChampionshipManagementController::class, 'storeRegistration'])->name('championships.registrations.store');
    Route::put('championships/registrations/{registration}/result', [ChampionshipManagementController::class, 'recordResult'])->name('championships.registrations.result');
    Route::put('championships/payments/{payment}/settle', [ChampionshipManagementController::class, 'settleRegistrationPayment'])->name('championships.payments.settle');
    Route::post('championships/{event}/coaches', [ChampionshipManagementController::class, 'storeCoachRegistration'])->name('championships.coaches.store');

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

    Route::redirect('my-profile', '/settings/profile');
    Route::get('achievements', [UserAchievementController::class, 'index'])->name('achievements.index');
    Route::post('achievements', [UserAchievementController::class, 'storeAchievement'])->name('achievements.store');
});

require __DIR__.'/settings.php';
