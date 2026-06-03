<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AdminManagementController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AttendanceManagementController;
use App\Http\Controllers\ChampionshipManagementController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ParentChildContextController;
use App\Http\Controllers\ParentChildProfileController;
use App\Http\Controllers\PaymentManagementController;
use App\Http\Controllers\ProfileAccessController;
use App\Http\Controllers\SessionManagementController;
use App\Http\Controllers\UserAchievementController;
use App\Http\Controllers\UsersManagementController;
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

Route::middleware(['auth', 'verified'])->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Core pages
    |--------------------------------------------------------------------------
    */

    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('components-playground', function () {
        return Inertia::render('ComponentsPlaygroundPage');
    })->name('components-playground');
    Route::redirect('coach-parent-management', '/users')->name('coach-parent.index');
    Route::redirect('my-profile', '/settings/profile');

    Route::prefix('announcements')
        ->name('announcements.')
        ->controller(AnnouncementController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
        });

    /*
    |--------------------------------------------------------------------------
    | Users, profiles, and parent links
    |--------------------------------------------------------------------------
    */

    Route::prefix('users')->group(function () {
        Route::get('/', [ProfileAccessController::class, 'usersIndex'])->name('athletes.index');
        Route::get('{user}', [ParentChildProfileController::class, 'show'])->name('athletes.show');
        Route::patch('{user}/account', [ProfileAccessController::class, 'updateAccount'])->name('users.account.update');
        Route::post('{user}/profile', [ProfileAccessController::class, 'updateAccountProfile'])->name('users.profile.update');
        Route::put('{user}/password', [ParentChildProfileController::class, 'updatePassword'])->name('users.password.update');
        Route::put('{user}/athlete-profile', [ProfileAccessController::class, 'updateAthleteProfile'])->name('users.athlete-profile.update');
        Route::put('{user}/coach-profile', [AdminManagementController::class, 'updateCoachProfile'])->name('users.coach-profile.update');
        Route::put('{user}/parent-profile', [AdminManagementController::class, 'updateParentProfile'])->name('users.parent-profile.update');
        Route::post('{user}/certifications', [ProfileAccessController::class, 'storeUserCertification'])->name('users.certifications.store');
        Route::put('{user}/certifications/{certification}', [ProfileAccessController::class, 'updateUserCertification'])->name('users.certifications.update');
        Route::post('{user}/achievements', [ProfileAccessController::class, 'storeUserAchievement'])->name('users.achievements.store');
        Route::put('{user}/achievements/{achievement}', [ProfileAccessController::class, 'updateUserAchievement'])->name('users.achievements.update');
    });

    Route::prefix('parents')->group(function () {
        Route::put('{parent}/children', [UsersManagementController::class, 'syncParentChildren'])->name('parents.children.sync');
    });

    Route::prefix('athlete')->group(function () {
        Route::post('/', [UsersManagementController::class, 'store'])->name('athletes.store');
        Route::get('{athlete}', [UsersManagementController::class, 'show'])->name('athletes.record.show');
        Route::put('{athlete}', [UsersManagementController::class, 'update'])->name('athletes.update');
        Route::delete('{athlete}', [UsersManagementController::class, 'destroy'])->name('athletes.destroy');
        Route::post('{athlete}/parent-link', [UsersManagementController::class, 'linkParent'])->name('athletes.parent-link');
        Route::get('user/{user}', [UsersManagementController::class, 'showByUser'])->name('users.show');
        Route::put('user/{user}', [UsersManagementController::class, 'upsertByUser'])->name('users.update');
    });
    /*
    |--------------------------------------------------------------------------
    | Admin workspace
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminManagementController::class, 'index'])->name('index');
        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::post('invoice-template', [AdminManagementController::class, 'updateInvoiceTemplate'])->name('invoice-template.update');

        Route::controller(AdminManagementController::class)->group(function () {
            Route::post('accounts', 'store')->name('accounts.store');
            Route::get('accounts/{user}', 'show')->name('accounts.show');
            Route::put('accounts/{user}', 'update')->name('accounts.update');
            Route::post('accounts/{user}/profile', 'updateAccountProfile')->name('accounts.profile.update');
            Route::delete('accounts/{user}', 'destroyAccount')->name('accounts.destroy');
            Route::put('accounts/{id}/restore', 'restoreAccount')->name('accounts.restore');
            Route::delete('accounts/{id}/hard-delete', 'hardDelete')->name('accounts.force-delete');

            Route::post('branches', 'storeBranch')->name('branches.store');
            Route::put('branches/{branch}', 'updateBranch')->name('branches.update');
            Route::delete('branches/{branch}', 'destroyBranch')->name('branches.destroy');

            Route::post('groups', 'storeGroup')->name('groups.store');
            Route::put('groups/{group}', 'updateGroup')->name('groups.update');
            Route::delete('groups/{group}', 'destroyGroup')->name('groups.destroy');

            Route::post('data-transfer/import', 'importCsv')->name('data-transfer.import');
            Route::get('data-transfer/export', 'exportCsv')->name('data-transfer.export');
            Route::get('data-transfer/template', 'downloadTemplate')->name('data-transfer.template');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Payments and attendance
    |--------------------------------------------------------------------------
    */

    Route::prefix('payments')
        ->name('payments.')
        ->controller(PaymentManagementController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
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
        ->controller(AttendanceManagementController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
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
        ->controller(ChampionshipManagementController::class)
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
        ->controller(SessionManagementController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::put('{session}', 'update')->name('update');
            Route::delete('{session}', 'destroy')->name('destroy');
            Route::post('{session}/join', 'join')->name('join');
            Route::get('{session}/attendance', 'attendanceSheet')->name('attendance');
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
            Route::post('{athlete}/switch', 'switch')->name('switch');
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
