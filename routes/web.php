<?php
use Inertia\Inertia;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\ChampionshipController;
use App\Http\Controllers\ChampionshipExportController;
use App\Http\Controllers\Admin\Features\AdminAttendanceReportController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Admin\Features\AdminEventFeatureController;
use App\Http\Controllers\Admin\Features\AdminFinanceFeatureController;
use App\Http\Controllers\Admin\Features\AdminPeopleFeatureController;
use App\Http\Controllers\Admin\Features\AdminScheduleFeatureController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\GroupController;
use App\Http\Controllers\Admin\InvoiceTemplateController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceScanController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ParentChildContextController;
use App\Http\Controllers\ParentChildProfileController;
use App\Http\Controllers\ProfileAccessController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SessionAttendanceQrController;
use App\Http\Controllers\UserAchievementController;
use App\Http\Controllers\Profiles\UserAchievementController as ProfileUserAchievementController;
use App\Http\Controllers\Profiles\CoachProfileController;
use App\Http\Controllers\Profiles\ParentProfileController;
use App\Http\Controllers\Training\TrainingClassController;
use App\Http\Controllers\Training\TrainingLocationController;
use App\Http\Controllers\Training\WeeklyScheduleController;
use App\Http\Controllers\Profiles\UserCertificationController;
use App\Http\Controllers\UserDirectoryController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => Inertia::render('Welcome'))->name('home');
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('training-schedule', [WeeklyScheduleController::class, 'index'])->name('training-schedule.index');
    Route::post('training-schedules', [WeeklyScheduleController::class, 'store'])->name('training-schedules.store');
    Route::put('training-schedules/{schedule}', [WeeklyScheduleController::class, 'update'])->name('training-schedules.update');
    Route::delete('training-schedules/{schedule}', [WeeklyScheduleController::class, 'destroy'])->name('training-schedules.destroy');
    Route::get('sessions', [SessionController::class, 'index'])->name('sessions.index');
    Route::post('sessions', [SessionController::class, 'store'])->name('sessions.store');
    Route::put('sessions/{session}', [SessionController::class, 'update'])->name('sessions.update');
    Route::delete('sessions/{session}', [SessionController::class, 'destroy'])->name('sessions.destroy');
    Route::post('sessions/{session}/join', [SessionController::class, 'join'])->name('sessions.join');
    Route::get('sessions/{session}/attendance', [SessionController::class, 'attendanceSheet'])->name('sessions.attendance');
    Route::post('sessions/{session}/attendance-qr', [SessionAttendanceQrController::class, 'store'])->name('sessions.attendance-qr.store');
    Route::delete('sessions/{session}/attendance-qr', [SessionAttendanceQrController::class, 'destroy'])->name('sessions.attendance-qr.destroy');
    Route::post('sessions/{session}/coach-attendance', [SessionController::class, 'addCoachAttendance'])->name('sessions.coach-attendance.store');
    Route::put('sessions/coach-attendance/{coachAttendance}', [SessionController::class, 'updateCoachAttendance'])->name('sessions.coach-attendance.update');
    Route::delete('sessions/coach-attendance/{coachAttendance}', [SessionController::class, 'destroyCoachAttendance'])->name('sessions.coach-attendance.destroy');
    Route::get('achievements', [UserAchievementController::class, 'index'])->name('achievements.index');
    Route::post('achievements', [UserAchievementController::class, 'storeAchievement'])->name('achievements.store');
    Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('announcements', [AnnouncementController::class, 'store'])->name('announcements.store');

    Route::middleware('throttle:qr-scan')->group(function () {
        Route::get('attendance/scan/{token}', [AttendanceScanController::class, 'show'])->name('attendance.scan.show');
        Route::post('attendance/scan/{token}', [AttendanceScanController::class, 'store'])->name('attendance.scan.store');
    });


    Route::prefix('users')->controller(UserDirectoryController::class)->group(function () {
        Route::get('/', 'index')->name('users.index');
        Route::post('/', 'store')->name('users.store');
        Route::get('{user}', [ProfileAccessController::class, 'show'])->name('users.show');
        Route::put('{user}', 'update')->name('users.update');
        Route::patch('{user}/account', [ProfileAccessController::class, 'updateAccount'])->name('users.account.update');
        Route::post('{user}/profile', [ProfileAccessController::class, 'updateAccountProfile'])->name('users.profile.update');
        Route::put('{user}/athlete-profile', [ProfileAccessController::class, 'updateAthleteProfile'])->name('users.athlete-profile.update');
        Route::put('{user}/coach-profile', [CoachProfileController::class, 'update'])->name('users.coach-profile.update');
        Route::put('{user}/parent-profile', [ParentProfileController::class, 'update'])->name('users.parent-profile.update');
        Route::put('{user}/password', [ParentChildProfileController::class, 'updatePassword'])->name('users.password.update');
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

    Route::prefix('parent/children')->name('parent.children.')->controller(ParentChildContextController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('switch', 'switch')->name('switch');
        Route::delete('switch', 'clear')->name('clear');
        Route::post('{athlete:athlete_id}/switch', 'switchAthlete')->name('switch-athlete');
    });

    Route::prefix('championships')->name('championships.')->controller(ChampionshipController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('events', 'storeEvent')->name('events.store');
        Route::get('{event}', 'show')->name('show');
        Route::get('{event}/export', ChampionshipExportController::class)->name('export');
        Route::post('registrations', 'storeRegistration')->name('registrations.store');
        Route::put('registrations/{registration}', 'updateRegistration')->name('registrations.update');
        Route::post('payments/{payment}/settle', 'settleRegistrationPayment')->name('payments.settle');
        Route::post('{event}/coaches', 'storeCoachRegistration')->name('coaches.store');
        Route::post('registrations/{registration}/result', 'recordResult')->name('registrations.result');
    });

    Route::prefix('athlete')->controller(UserDirectoryController::class)->group(function () {
        Route::post('/', 'store')->name('athletes.store');
        Route::get('{athlete}', 'show')->name('athletes.record.show');
        Route::put('{athlete}', 'update')->name('athletes.update');
        Route::delete('{athlete}', 'destroy')->name('athletes.destroy');
        Route::post('{athlete}/parent-link', 'linkParent')->name('athletes.parent-link');
        Route::get('user/{user}', 'showByUser')->name('users.athlete-record.show');
        Route::put('user/{user}', 'upsertByUser')->name('users.athlete-record.update');
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
        Route::post('manual', 'store')->name('manual.store');
        Route::post('manual', 'store')->name('store');
        Route::post('bulk-update', 'bulkUpdate')->name('bulk-update');
        Route::put('{attendance}', 'update')->name('update');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
