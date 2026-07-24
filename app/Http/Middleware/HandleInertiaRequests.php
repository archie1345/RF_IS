<?php

namespace App\Http\Middleware;

use App\Services\ActiveRoleContextService;
use App\Services\ParentChildContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $roleContext = app(ActiveRoleContextService::class);
        $childContext = app(ParentChildContextService::class);
        $roles = $roleContext->availableRoles($user);
        $activeRole = $roleContext->activeRole($request, $user);
        $primaryRole = $roles[0] ?? $activeRole;
        $children = $activeRole === 'parent' ? $childContext->sharedChildrenFor($user) : collect();
        $activeChild = $activeRole === 'parent' ? $childContext->activeChildFor($request) : null;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    // `role` remains a compatibility alias for the selected role so existing
                    // pages become multi-role aware without reading the legacy users.role column.
                    'role' => $activeRole,
                    'activeRole' => $activeRole,
                    'primaryRole' => $primaryRole,
                    'roles' => $roles,
                    'isMultiRole' => count($roles) > 1,
                    'avatar' => $user->profile?->profile_picture_path
                        ? Storage::url($user->profile->profile_picture_path)
                        : null,
                ] : null,
                'children' => $children,
                'activeChild' => $activeChild,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
                'attendanceQr' => fn () => $request->session()->get('attendanceQr'),
                'attendanceQrStatus' => fn () => $request->session()->get('attendanceQrStatus'),
                'attendanceScan' => fn () => $request->session()->get('attendanceScan'),
            ],
        ];
    }
}
