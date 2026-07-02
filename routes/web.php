<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\GroupController;
use App\Http\Controllers\Admin\InvoiceTemplateController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceScanController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\ChampionshipController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ParentChildContextController;
use App\Http\Controllers\ParentChildProfileController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileAccessController;
use App\Http\Controllers\Profiles\AthleteProfileController;
use App\Http\Controllers\Profiles\CoachProfileController;
use App\Http\Controllers\Profiles\ParentProfileController;
use App\Http\Controllers\Profiles\UserAccountController;
use App\Http\Controllers\Profiles\UserAchievementController as ProfileUserAchievementController;
use App\Http\Controllers\Profiles\UserCertificationController;
use App\Http\Controllers\SessionAttendanceQrController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\UserAchievementController;
use App\Http\Controllers\UserDirectoryController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

/*
|--------------------------------------------------------------------------
| Public pages
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('invitations/{token}', [InvitationController::class, 'show'])
        ->middleware('throttle:6,1')
        ->name('invitations.show');
    Route::post('invitations/{token}', [InvitationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('invitations.accept');
});

Route::middleware(['auth', 'account.active', 'verified'])->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Core pages
    |--------------------------------------------------------------------------
    */

    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::redirect('coach-parent-management', '/users')->name('coach-parent.index');
    Route::redirect('my-profile', '/settings/profile');

    Route::prefix('announcements')
        ->name('announcements.')
        ->controller(AnnouncementController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('scan/{token}', [AttendanceScanController::class, 'show'])->name('scan.show');
            Route::post('scan/{token}', [AttendanceScanController::class, 'store'])->name('scan.store');
            Route::post('/', 'store')->name('store');
        });

    /*
    |--------------------------------------------------------------------------
    | Users, profiles, and parent links
    |--------------------------------------------------------------------------
    */

    Route::prefix('users')->group(function () {
        Route::get('/', [ProfileAccessController::class, 'usersIndex'])->name('users.index');
        Route::get('{user}', [ParentChildProfileController::class, 'show'])->name('users.show');
        Route::put('{user}/password', [ParentChildProfileController::class, 'updatePassword'])->name('users.password.update');

        Route::patch('{user}/account', [UserAccountController::class, 'update'])->name('users.account.update');
        Route::post('{user}/profile', [UserAccountController::class, 'updateProfile'])->name('users.profile.update');
        Route::put('{user}/athlete-profile', [AthleteProfileController::class, 'update'])->name('users.athlete-profile.update');
        Route::put('{user}/coach-profile', [CoachProfileController::class, 'update'])->name('users.coach-profile.update');
        Route::put('{user}/parent-profile', [ParentProfileController::class, 'update'])->name('users.parent-profile.update');

        Route::post('{user}/certifications', [UserCertificationController::class, 'store'])->name('users.certifications.store');
        Route::put('{user}/certifications/{certification}', [UserCertificationController::class, 'update'])->name('users.certifications.update');
        Route::post('{user}/achievements', [ProfileUserAchievementController::class, 'store'])->name('users.achievements.store');
        Route::put('{user}/achievements/{achievement}', [ProfileUserAchievementController::class, 'update'])->name('users.achievements.update');
    });

    Route::prefix('parents')->group(function () {
        Route::put('{parent:parent_id}/children', [UserDirectoryController::class, 'syncParentChildren'])->name('parents.children.sync');
    });

    Route::prefix('athlete')
        ->controller(UserDirectoryController::class)
        ->group(function () {
            Route::post('/', 'store')->name('athletes.store');
            Route::get('{athlete}', 'show')->name('athletes.record.show');
            Route::put('{athlete}', 'update')->name('athletes.update');
            Route::delete('{athlete}', 'destroy')->name('athletes.destroy');
            Route::post('{athlete}/parent-link', 'linkParent')->name('athletes.parent-link');
            Route::get('user/{user}', 'showByUser')->name('users.athlete-record.show');
            Route::put('user/{user}', 'upsertByUser')->name('users.update');
        });
    /*
    |--------------------------------------------------------------------------
    | Admin workspace
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');
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
            Route::post('groups', 'store')->name('groups.store');
            Route::put('groups/{group}', 'update')->name('groups.update');
            Route::delete('groups/{group}', 'destroy')->name('groups.destroy');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Payments and attendance
    |--------------------------------------------------------------------------
    */

    Route::prefix('payments')
        ->name('payments.')
        ->controller(PaymentController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('scan/{token}', [AttendanceScanController::class, 'show'])->name('scan.show');
            Route::post('scan/{token}', [AttendanceScanController::class, 'store'])->name('scan.store');
            Route::post('/', 'store')->name('store');
            Route::put('{payment}', 'update')->name('update');
            Route::delete('{payment}', 'destroy')->name('destroy');
            Route::put('{payment}/status', 'updateStatus')->name('status.update');
            Route::post('{payment}/proof', 'submitProof')->name('proof.submit');
            Route::put('{payment}/proof-review', 'reviewProof')->name('proof.review');
            Route::get('{payment}/export', 'exportInvoice')->name('export');
        });

    Route::prefix('attendance')
        ->name('attendance.')
        ->controller(AttendanceController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('scan/{token}', [AttendanceScanController::class, 'show'])->name('scan.show');
            Route::post('scan/{token}', [AttendanceScanController::class, 'store'])->name('scan.store');
            Route::post('/', 'store')->name('store');
            Route::post('bulk-update', 'bulkUpdate')->name('bulk-update');
            Route::put('{attendance}', 'update')->name('update');
        });

    /*
    |--------------------------------------------------------------------------
    | Championships and sessions
    |--------------------------------------------------------------------------
    */

    Route::prefix('championships')
        ->name('championships.')
        ->controller(ChampionshipController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('events', 'storeEvent')->name('events.store');
            Route::post('registrations', 'storeRegistration')->name('registrations.store');
            Route::put('registrations/{registration}/result', 'recordResult')->name('registrations.result');
            Route::put('payments/{payment}/settle', 'settleRegistrationPayment')->name('payments.settle');
            Route::post('{event}/coaches', 'storeCoachRegistration')->name('coaches.store');
            Route::get('{event}', 'show')->name('show');
        });

    Route::prefix('sessions')
        ->name('sessions.')
        ->controller(SessionController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::put('{session}', 'update')->name('update');
            Route::delete('{session}', 'destroy')->name('destroy');
            Route::post('{session}/join', 'join')->name('join');
            Route::get('{session}/attendance', 'attendanceSheet')->name('attendance');
            Route::post('{session}/attendance-qr', [SessionAttendanceQrController::class, 'store'])->name('attendance-qr.store');
            Route::delete('{session}/attendance-qr', [SessionAttendanceQrController::class, 'destroy'])->name('attendance-qr.destroy');
            Route::post('{session}/coach-attendance', 'addCoachAttendance')->name('coach-attendance.store');
            Route::put('coach-attendance/{coachAttendance}', 'updateCoachAttendance')->name('coach-attendance.update');
            Route::delete('coach-attendance/{coachAttendance}', 'destroyCoachAttendance')->name('coach-attendance.destroy');
        });

    /*
    |--------------------------------------------------------------------------
    | Parent context and achievements
    |--------------------------------------------------------------------------
    */

    Route::prefix('parent/children')
        ->name('parent.children.')
        ->controller(ParentChildContextController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('switch', 'switch')->name('switch');
            Route::post('{athlete}/switch', 'switchAthlete')->name('switch-athlete');
            Route::delete('switch', 'clear')->name('clear');
        });

    Route::prefix('achievements')
        ->name('achievements.')
        ->controller(UserAchievementController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'storeAchievement')->name('store');
        });
});

require __DIR__.'/settings.php';
