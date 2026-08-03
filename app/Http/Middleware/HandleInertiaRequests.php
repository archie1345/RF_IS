<?php

namespace App\Http\Middleware;

use App\Services\ActiveRoleContextService;
use App\Services\ParentChildContextService;
use App\Services\PublicContactSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        $publicContact = app(PublicContactSettings::class);

        return [
            ...parent::share($request),
            'publicWhatsappBubbleEnabled' => fn (): bool => $publicContact->bubbleEnabled(),
            'name' => config('app.name'),
            'auth' => fn (): array => $this->sharedAuth($request),
            'publicAdminWhatsapp' => fn (): string => $publicContact->contactNumber(),
            'publicWhatsappBubbleEnabled' => fn (): bool => $publicContact->bubbleEnabled(),
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

    /** @return array<string, mixed> */
    private function sharedAuth(Request $request): array
    {
        $user = $request->user();
        $roleContext = app(ActiveRoleContextService::class);
        $childContext = app(ParentChildContextService::class);
        $roles = $roleContext->availableRoles($user);
        $activeRole = $roleContext->activeRole($request, $user);
        $primaryRole = $roles[0] ?? $activeRole;
        $children = $activeRole === 'parent' ? $childContext->sharedChildrenFor($user) : collect();

        return [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
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
            // Parent pages now show all children and filter locally instead of
            // carrying one persistent child context through the application.
            'activeChild' => null,
        ];
    }
}
