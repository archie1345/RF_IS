<?php

use App\Http\Controllers\Admin\GroupController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'account.active'])
    ->get('/user', fn (Request $request) => $request->user());

// JSON endpoint used by the admin class member modal. The web middleware is
// intentional: the Inertia frontend authenticates with the normal session.
Route::middleware(['web', 'auth', 'account.active', 'verified', 'role:admin,coach'])
    ->get('/admin/groups/{group}/athletes', [GroupController::class, 'athletes'])
    ->name('api.admin.groups.athletes');

Route::get('/cron/schedule', function (Request $request) {
    $secret = trim((string) config('services.cron.secret', ''));

    if ($secret === '') {
        abort(404);
    }

    $provided = (string) $request->bearerToken();

    if ($provided === '' || ! hash_equals($secret, $provided)) {
        abort(401);
    }

    $task = (string) $request->query('task', 'schedule');

    $exitCode = match ($task) {
        'sessions' => Artisan::call('sessions:generate-from-weekly', ['--days' => 14]),
        'tuition' => Artisan::call('tuition:generate-monthly'),
        'prune' => Artisan::call('activity-logs:prune', ['--days' => 90]),
        default => Artisan::call('schedule:run'),
    };

    return response()->json([
        'ok' => $exitCode === 0,
        'task' => $task,
        'exit_code' => $exitCode,
        'output' => trim(Artisan::output()),
    ], $exitCode === 0 ? 200 : 500);
})->name('api.cron.schedule');
