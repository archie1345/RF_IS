<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Admin\AdminAccountController;
use App\Http\Controllers\Admin\AdminAccountLifecycleController;
use App\Http\Controllers\Admin\AdminPageController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\Features\AdminAttendanceReportController;
use App\Http\Controllers\Admin\Features\AdminEventFeatureController;
use App\Http\Controllers\Admin\Features\AdminFinanceFeatureController;
use App\Http\Controllers\Admin\Features\AdminPeopleFeatureController;
use App\Http\Controllers\Admin\Features\AdminScheduleFeatureController;
use App\Http\Controllers\Admin\GroupController;
use App\Http\Controllers\Admin\InvoiceTemplateController;
use App\Http\Controllers\Admin\TrainingGroupController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceScanController;
use App\Http\Controllers\ChampionshipController;
use App\Http\Controllers\ChampionshipExportController;
use App\Http\Controllers\ChampionshipPageController;
use App\Http\Controllers\ChampionshipPaymentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ParentChildContextController;
use App\Http\Controllers\ParentChildProfileController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentExportController;
use App\Http\Controllers\PaymentPageController;
use App\Http\Controllers\ProfileAccessController;
use App\Http\Controllers\Profiles\CoachProfileController;
use App\Http\Controllers\Profiles\ParentProfileController;
use App\Http\Controllers\Profiles\UserAchievementController as ProfileUserAchievementController;
use App\Http\Controllers\Profiles\UserCertificationController;
use App\Http\Controllers\QrisPaymentPageController;
use App\Http\Controllers\SessionAttendanceQrController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\Training\TrainingClassController;
use App\Http\Controllers\Training\TrainingLocationController;
use App\Http\Controllers\Training\WeeklyScheduleController;
use App\Http\Controllers\Training\WeeklySchedulePageController;
use App\Http\Controllers\UserAchievementController;
use App\Http\Controllers\UserDirectoryController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome'))->name('home');

Route::middleware(['auth', 'account.active', 'verified'])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('training-schedule', WeeklySchedulePageController::class)->name('training-schedule.index');
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
    Route::put('achievements/{achievement}', [UserAchievementController::class, 'updateAchievement'])->name('achievements.update');
    Route::delete('achievements/{achievement}', [UserAchievementController::class, 'destroyAchievement'])->name('achievements.destroy');

    Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::middleware('role:admin')->group(function (): void {
        Route::post('announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::put('announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
        Route::delete('announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
    });

    Route::middleware('throttle:qr-scan')->group(function (): void {
        Route::get('attendance/scan/{token}', [AttendanceScanController::class, 'show'])->name('attendance.scan.show');
        Route::post('attendance/scan/{token}', [AttendanceScanController::class, 'store'])->name('attendance.scan.store');
    });

    Route::prefix('users')->group(function (): void {
        Route::get('{user}', [ProfileAccessController::class, 'show'])->name('users.show');
        Route::patch('{user}/account', [ProfileAccessController::class, 'updateAccount'])->name('users.account.update');
        Route::post('{user}/profile', [ProfileAccessController::class, 'updateAccountProfile'])->name('users.profile.update');
        Route::put('{user}/athlete-profile', [ProfileAccessController::class, 'updateAthleteProfile'])->name('users.athlete-profile.update');
        Route::put('{user}/coach-profile', [CoachProfileController::class, 'update'])->name('users.coach-profile.update');
        Route::put('{user}/parent-profile', [ParentProfileController::class, 'update'])->name('users.parent-profile.update');
        Route::put('{user}/password', [ParentChildProfileController::class, 'updatePassword'])->name('users.password.update');
        Route::post('{user}/certifications', [UserCertificationController::class, 'store'])->name('users.certifications.store');
        Route::put('{user}/certifications/{certification}', [UserCertificationController::class, 'update'])->name('users.certifications.update');
        Route::delete('{user}/certifications/{certification}', [UserCertificationController::class, 'destroy'])->name('users.certifications.destroy');
        Route::post('{user}/achievements', [ProfileUserAchievementController::class, 'store'])->name('users.achievements.store');
        Route::put('{user}/achievements/{achievement}', [ProfileUserAchievementController::class, 'update'])->name('users.achievements.update');
        Route::delete('{user}/achievements/{achievement}', [ProfileUserAchievementController::class, 'destroy'])->name('users.achievements.destroy');
    });

    Route::middleware('role:admin')->group(function (): void {
        Route::prefix('users')->controller(UserDirectoryController::class)->group(function (): void {
            Route::get('/', 'index')->name('users.index');
            Route::post('/', 'store')->name('users.store');
            Route::put('{user}', 'update')->name('users.update');
            Route::delete('{user}', 'destroy')->name('users.destroy');
            Route::post('{user}/restore', 'restore')->name('users.restore');
            Route::delete('{user}/force', 'forceDelete')->name('users.force-delete');
        });

        Route::put('parents/{parent:parent_id}/children', [UserDirectoryController::class, 'syncParentChildren'])
            ->name('parents.children.sync');

        Route::prefix('athlete')->controller(UserDirectoryController::class)->group(function (): void {
            Route::post('/', 'store')->name('athletes.store');
            Route::get('{athlete}', 'show')->name('athletes.record.show');
            Route::put('{athlete}', 'update')->name('athletes.update');
            Route::delete('{athlete}', 'destroy')->name('athletes.destroy');
            Route::post('{athlete}/parent-link', 'linkParent')->name('athletes.parent-link');
            Route::get('user/{user}', 'showByUser')->name('users.athlete-record.show');
            Route::put('user/{user}', 'upsertByUser')->name('users.athlete-record.update');
        });
    });

    Route::prefix('parent/children')
        ->name('parent.children.')
        ->middleware('role:parent')
        ->controller(ParentChildContextController::class)
        ->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::post('switch', 'switch')->name('switch');
            Route::delete('switch', 'clear')->name('clear');
            Route::post('{athlete:athlete_id}/switch', 'switchAthlete')->name('switch-athlete');
        });

    Route::prefix('championships')->name('championships.')->group(function (): void {
        Route::get('/', ChampionshipPageController::class)->name('index');
        Route::get('{event}', [ChampionshipController::class, 'show'])->name('show');

        Route::middleware('role:admin')->group(function (): void {
            Route::post('events', [ChampionshipController::class, 'storeEvent'])->name('events.store');
            Route::put('events/{event}', [ChampionshipController::class, 'updateEvent'])->name('events.update');
            Route::delete('events/{event}', [ChampionshipController::class, 'destroyEvent'])->name('events.destroy');
            Route::post('payments/{payment}/settle', ChampionshipPaymentController::class)->name('payments.settle');
        });

        Route::middleware('role:admin,parent,athlete')->group(function (): void {
            Route::post('registrations', [ChampionshipController::class, 'storeRegistration'])->name('registrations.store');
        });

        Route::middleware('role:admin,coach')->group(function (): void {
            Route::get('{event}/export', ChampionshipExportController::class)->name('export');
            Route::put('registrations/{registration}', [ChampionshipController::class, 'updateRegistration'])->name('registrations.update');
            Route::delete('registrations/{registration}', [ChampionshipController::class, 'destroyRegistration'])->name('registrations.destroy');
            Route::post('{event}/coaches', [ChampionshipController::class, 'storeCoachRegistration'])->name('coaches.store');
            Route::delete('coaches/{coachRegistration}', [ChampionshipController::class, 'destroyCoachRegistration'])->name('coaches.destroy');
            Route::post('registrations/{registration}/result', [ChampionshipController::class, 'recordResult'])->name('registrations.result');
        });
    });

    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function (): void {
        Route::get('/', AdminPageController::class)->name('index');
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('attendance', [AdminAttendanceReportController::class, 'athletes'])->name('attendance');
        Route::get('attendance/export', [AdminAttendanceReportController::class, 'exportAthletes'])->name('attendance.export');
        Route::get('instructor-attendance', [AdminAttendanceReportController::class, 'coaches'])->name('instructor-attendance');
        Route::post('instructor-attendance/manual', [AdminAttendanceReportController::class, 'storeCoachAttendance'])->name('instructor-attendance.manual');
        Route::get('instructor-attendance/export', [AdminAttendanceReportController::class, 'exportCoaches'])->name('instructor-attendance.export');
        Route::redirect('payments', '/payments')->name('payments');
        Route::redirect('finance-income', '/payments')->name('finance-income');
        Route::redirect('finance-output', '/payments')->name('finance-output');
        Route::redirect('monthly-dues', '/payments')->name('monthly-dues');
        Route::post('monthly-dues/settings', [AdminFinanceFeatureController::class, 'updateBillingSettings'])->name('monthly-dues.settings');
        Route::post('monthly-dues/generate', [AdminFinanceFeatureController::class, 'generateMonthlyDues'])->name('monthly-dues.generate');
        Route::get('members', [AdminPeopleFeatureController::class, 'members'])->name('members');
        Route::get('instructors', [AdminPeopleFeatureController::class, 'instructors'])->name('instructors');
        Route::redirect('events', '/championships')->name('events');
        Route::get('events/history', [AdminEventFeatureController::class, 'history'])->name('events.history');
        Route::redirect('events/schedule', '/championships')->name('events.schedule');
        Route::get('locations', [TrainingLocationController::class, 'index'])->name('locations');
        Route::get('classes', [TrainingClassController::class, 'index'])->name('classes');
        Route::get('groups', [TrainingGroupController::class, 'index'])->name('groups');
        Route::post('training-groups', [TrainingGroupController::class, 'store'])->name('training-groups.store');
        Route::put('training-groups/{trainingGroup}', [TrainingGroupController::class, 'update'])->name('training-groups.update');
        Route::delete('training-groups/{trainingGroup}', [TrainingGroupController::class, 'destroy'])->name('training-groups.destroy');
        Route::redirect('schedules', '/training-schedule')->name('schedules');
        Route::post('schedules', [WeeklyScheduleController::class, 'store'])->name('schedules.store');
        Route::post('schedules/generate-week', [WeeklyScheduleController::class, 'generate'])->name('schedules.generate-week');
        Route::get('daily-schedules', [AdminScheduleFeatureController::class, 'daily'])->name('daily-schedules');
        Route::redirect('periodic-stats', '/admin/dashboard')->name('periodic-stats');
        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::post('invoice-template', [InvoiceTemplateController::class, 'update'])->name('invoice-template.update');

        Route::post('accounts', [AdminAccountController::class, 'store'])->name('accounts.store');
        Route::put('accounts/{user}', [AdminAccountController::class, 'update'])->name('accounts.update');
        Route::get('accounts/{user}', [AdminController::class, 'show'])->name('accounts.show');
        Route::post('accounts/{user}/profile', [AdminController::class, 'updateAccountProfile'])->name('accounts.profile.update');
        Route::post('accounts/{user}/invitation', [AdminAccountLifecycleController::class, 'resendInvitation'])->name('accounts.invitation.resend');
        Route::delete('accounts/{user}', [AdminAccountLifecycleController::class, 'destroy'])->name('accounts.destroy');
        Route::put('accounts/{id}/restore', [AdminAccountLifecycleController::class, 'restore'])->name('accounts.restore');
        Route::delete('accounts/{id}/hard-delete', [AdminAccountLifecycleController::class, 'forceDelete'])->name('accounts.force-delete');

        Route::controller(BranchController::class)->group(function (): void {
            Route::post('branches', 'store')->name('branches.store');
            Route::put('branches/{branch}', 'update')->name('branches.update');
            Route::delete('branches/{branch}', 'destroy')->name('branches.destroy');
        });

        Route::controller(GroupController::class)->group(function (): void {
            Route::get('groups/{group}/athletes', 'athletes')->name('groups.athletes');
            Route::post('groups', 'store')->name('groups.store');
            Route::put('groups/{group}', 'update')->name('groups.update');
            Route::delete('groups/{group}', 'destroy')->name('groups.destroy');
        });
    });

    Route::prefix('payments')->name('payments.')->group(function (): void {
        Route::get('/', PaymentPageController::class)->name('index');
        Route::get('qris', QrisPaymentPageController::class)->name('qris');
        Route::post('{payment}/proof', [PaymentController::class, 'submitProof'])->name('proof.submit');
        Route::get('{payment}/export', [PaymentController::class, 'exportInvoice'])->name('export');

        Route::middleware('role:admin')->group(function (): void {
            Route::get('export', PaymentExportController::class)->name('export.csv');
            Route::post('/', [PaymentController::class, 'store'])->name('store');
            Route::put('{payment}', [PaymentController::class, 'update'])->name('update');
            Route::delete('{payment}', [PaymentController::class, 'destroy'])->name('destroy');
            Route::post('{payment}/transactions', [PaymentController::class, 'recordPayment'])->name('transactions.store');
            Route::put('{payment}/status', [PaymentController::class, 'updateStatus'])->name('status.update');
            Route::put('{payment}/proof-review', [PaymentController::class, 'reviewProof'])->name('proof.review');
        });
    });

    Route::prefix('attendance')->name('attendance.')->controller(AttendanceController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('coach-sessions/{session}/attend', 'attendAsCoach')->name('coach-attend');
        Route::post('manual', 'store')->name('store');
        Route::post('bulk-update', 'bulkUpdate')->name('bulk-update');
        Route::put('{attendance}', 'update')->name('update');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
