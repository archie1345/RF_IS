<?php

namespace App\Http\Middleware;

use App\Models\Athlete;
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
        $activeChildId = $request->session()->get('active_child_id');
        $children = [];
        $activeChild = null;

        if ($user && $user->isParent()) {
            $children = $user->children()
                ->with('user:id,name')
                ->orderBy('athlete_id')
                ->get()
                ->map(fn (Athlete $athlete) => [
                    'athlete_id' => $athlete->athlete_id,
                    'user_id' => $athlete->id,
                    'name' => $athlete->user?->name ?? 'Unknown athlete',
                ])
                ->values();

            if ($activeChildId !== null) {
                $activeChild = $children->firstWhere('athlete_id', (string) $activeChildId);
            }
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    ...$user->toArray(),
                    'roles' => $user->assignedRoles(),
                    'avatar' => $user->profile?->profile_picture_path ? Storage::url($user->profile->profile_picture_path) : null,
                ] : null,
                'children' => $children,
                'activeChild' => $activeChild,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
