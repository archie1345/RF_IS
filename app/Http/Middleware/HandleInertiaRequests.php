<?php

namespace App\Http\Middleware;

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
        $childContext = app(ParentChildContextService::class);
        $children = $childContext->sharedChildrenFor($user);
        $activeChild = $childContext->activeChildFor($request);

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    // Keep every client-side role decision aligned with the resolver used by
                    // policies and controllers. The legacy column is only a fallback now that
                    // accounts can have role assignments.
                    'role' => $user->primaryRole(),
                    'roles' => $user->assignedRoles(),
                    'avatar' => $user->profile?->profile_picture_path ? Storage::url($user->profile->profile_picture_path) : null,
                ] : null,
                'children' => $children,
                'activeChild' => $activeChild,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'attendanceQr' => fn () => $request->session()->get('attendanceQr'),
                'attendanceQrStatus' => fn () => $request->session()->get('attendanceQrStatus'),
                'attendanceScan' => fn () => $request->session()->get('attendanceScan'),
            ],
        ];
    }
}
