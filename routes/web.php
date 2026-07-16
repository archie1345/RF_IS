<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Admin\AdminAttendanceReportController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminEventFeatureController;
use App\Http\Controllers\Admin\AdminFinanceFeatureController;
use App\Http\Controllers\Admin\AdminPeopleFeatureController;
use App\Http\Controllers\Admin\AdminScheduleFeatureController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\GroupController;
use App\Http\Controllers\Admin\InvoiceTemplateController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceScanController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileUserAchievementController;
use App\Http\Controllers\Training\TrainingClassController;
use App\Http\Controllers\Training\TrainingLocationController;
use App\Http\Controllers\Training\WeeklyScheduleController;
use App\Http\Controllers\UserCertificationController;
use App\Http\Controllers\UserDirectoryController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('training-schedule', [WeeklyScheduleController::class, 'index'])->name('training-schedule');
    Route::get('sessions', [AdminScheduleFeatureController::class, 'daily'])->name('sessions');
    Route::get('announcements', [DashboardController::class, 'announcements'])->name('announcements');

    Route::middleware('throttle:qr-scan')->group(function () {
        Route::get('attendance/scan/{token}', [AttendanceScanController::class, 'show'])->name('attendance.scan.show');
        Route::post('attendance/scan/{token}', [AttendanceScanController::class, 'store'])->name('attendance.scan.store');
    });

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::prefix('users')->controller(UserDirectoryController::class)->group(function () {
        Route::get('/', 'index')->name('users.index');
        Route::post('/', 'store')->name('users.store');
        Route::get('{user}', 'show')->name('users.show');
        Route::put('{user}', 'update')->name('users.update');
        Route::delete('{user}', 'destroy')->name('users.destroy');
        Route::post('{user}/restore', 'restore')->name('users.restore');
        Route::delete('{user}/force', 'forceDelete')->name('users.force-delete');
        Route::post('{user}/certifications', [UserCertificationController::class, 'store'])->name('users.certifications.store');
        Route::put('{user}/certifications/{certification}', [UserCertificationController::class, 'update'])->name('users.certifications.update');
        Route::post('{user}/achievements', [ProfileUserAchievementController::class, 'store'])->name('users.achievements.store');
        Route::put('{user}/achievements/{achievement}', [ProfileUserAchievementController::class, 'update'])->name('users.achievements.update');
    });

    Route::prefix('parents')->group(function () {
        Route::put('{parent:parent_id}/children', [UserDirectoryController::class, 'syncParentChildren'])->name('parents.children.sync');
    });

    Route::prefix('athlete')->controller(UserDirectoryController::class)->group(function () {
        Route::post('/', 'store')->name('athletes.store');
        Route::get('{athlete}', 'show')->name('athletes.record.show');
        Route::put('{athlete}', 'update')->name('athletes.update');
        Route::delete('{athlete}', 'destroy')->name('athletes.destroy');
        Route::post('{athlete}/parent-link', 'linkParent')->name('athletes.parent-link');
        Route::get('user/{user}', 'showByUser')->name('users.athlete-record.show');
        Route::put('user/{user}', 'upsertByUser')->name('users.update');
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('attendance', [AdminAttendanceReportController::class, 'athletes'])->name('attendance');
        Route::get('attendance/export', [AdminAttendanceReportController::class, 'exportAthletes'])->name('attendance.export');
        Route::get('instructor-attendance', [AdminAttendanceReportController::class, 'coaches'])->name('instructor-attendance');
        Route::get('instructor-attendance/export', [AdminAttendanceReportController::class, 'exportCoaches'])->name('instructor-attendance.export');
        Route::get('payments', [AdminFinanceFeatureController::class, 'index'])->name('payments');
        Route::redirect('finance-income', '/admin/payments')->name('finance-income');
        Route::redirect('finance-output', '/admin/payments')->name('finance-output');
        Route::redirect('monthly-dues', '/admin/payments')->name('monthly-dues');
        Route::post('monthly-dues/settings', [AdminFinanceFeatureController::class, 'updateBillingSettings'])->name('monthly-dues.settings');
        Route::post('monthly-dues/generate', [AdminFinanceFeatureController::class, 'generateMonthlyDues'])->name('monthly-dues.generate');
        Route::get('members', [AdminPeopleFeatureController::class, 'members'])->name('members');
        Route::get('instructors', [AdminPeopleFeatureController::class, 'instructors'])->name('instructors');
        Route::get('events', [AdminEventFeatureController::class, 'index'])->name('events');
        Route::get('events/history', [AdminEventFeatureController::class, 'history'])->name('events.history');
        Route::redirect('events/schedule', '/admin/events')->name('events.schedule');
        Route::get('locations', [TrainingLocationController::class, 'index'])->name('locations');
        Route::get('classes', [TrainingClassController::class, 'index'])->name('classes');
        Route::redirect('groups', '/admin/classes')->name('groups');
        Route::redirect('schedules', '/training-schedule')->name('schedules');
        Route::post('schedules', [WeeklyScheduleController::class, 'store'])->name('schedules.store');
        Route::post('schedules/generate-week', [WeeklyScheduleController::class, 'generate'])->name('schedules.generate-week');
        Route::get('daily-schedules', [AdminScheduleFeatureController::class, 'daily'])->name('daily-schedules');
        Route::redirect('periodic-stats', '/admin/dashboard')->name('periodic-stats');
        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::post('invoice-template', [InvoiceTemplateController::class, 'update'])->name('invoice-template.update');

        Route::controller(AdminController::class)->group(function () {
            Route::post('accounts', 'store')->name('accounts.store');
            Route::get('accounts/{user}', 'show')->name('accounts.show');
            Route::put('accounts/{user}', 'update')->name('accounts.update');
            Route::post('accounts/{user}/invitation', 'resendInvitation')->name('accounts.invitation.resend');
            Route::delete('accounts/{user}', 'destroyAccount')->name('accounts.destroy');
            Route::post('accounts/{user}/profile', 'updateAccountProfile')->name('accounts.profile.update');
            Route::put('accounts/{id}/restore', 'restoreAccount')->name('accounts.restore');
            Route::delete('accounts/{id}/hard-delete', 'hardDelete')->name('accounts.force-delete');
            Route::post('data-transfer/import', 'importCsv')->name('data-transfer.import');
            Route::get('data-transfer/export', 'exportCsv')->name('data-transfer.export');
            Route::get('data-transfer/template', 'downloadTemplate')->name('data-transfer.template');
        });

        Route::controller(BranchController::class)->group(function () {
            Route::post('branches', 'store')->name('branches.store');
            Route::put('branches/{branch}', 'update')->name('branches.update');
            Route::delete('branches/{branch}', 'destroy')->name('branches.destroy');
        });
        Route::controller(GroupController::class)->group(function () {
            Route::get('groups/{group}/athletes', 'athletes')->name('groups.athletes');
            Route::post('groups', 'store')->name('groups.store');
            Route::put('groups/{group}', 'update')->name('groups.update');
            Route::delete('groups/{group}', 'destroy')->name('groups.destroy');
        });
    });

    Route::prefix('payments')->name('payments.')->controller(PaymentController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('{payment}', 'update')->name('update');
        Route::delete('{payment}', 'destroy')->name('destroy');
        Route::put('{payment}/status', 'updateStatus')->name('status.update');
        Route::post('{payment}/proof', 'submitProof')->name('proof.submit');
        Route::put('{payment}/proof-review', 'reviewProof')->name('proof.review');
        Route::get('{payment}/export', 'exportInvoice')->name('export');
    });

    Route::prefix('attendance')->name('attendance.')->controller(AttendanceController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('scan/{token}', [AttendanceScanController::class, 'store'])->name('scan.store');
        Route::post('manual', 'storeManual')->name('manual.store');
        Route::put('{attendance}', 'update')->name('update');
    });
});

if (file_exists(__DIR__.'/auth.php')) {
    require __DIR__.'/auth.php';
}
