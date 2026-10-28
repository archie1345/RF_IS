<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsOverviewController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $user->loadCount(['certifications', 'achievements']);
        $roles = $user->assignedRoles();

        return Inertia::render('settings/Overview', [
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'email_verified' => $user->hasVerifiedEmail(),
                'account_status' => $user->account_status,
                'roles' => $roles,
                'certifications_count' => $user->certifications_count,
                'achievements_count' => $user->achievements_count,
                'two_factor_enabled' => filled($user->two_factor_secret),
            ],
        ]);
    }
}
