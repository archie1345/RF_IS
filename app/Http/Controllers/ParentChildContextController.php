<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ParentChildContextController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user && $user->isParent(), 403);

        $activeChildId = $request->session()->get('active_child_id');
        $children = $user->children()
            ->with(['user:id,name,email', 'branch:branch_id,branch_name', 'group:group_id,group_name'])
            ->orderBy('athlete_id')
            ->get()
            ->map(fn (Athlete $athlete) => [
                'athlete_id' => $athlete->athlete_id,
                'user_id' => $athlete->id,
                'name' => $athlete->user?->name ?? 'Unknown athlete',
                'email' => $athlete->user?->email ?? '-',
                'branch' => $athlete->branch?->branch_name ?? 'Unassigned',
                'group' => $athlete->group?->group_name ?? 'Unassigned',
                'is_active' => $activeChildId === $athlete->athlete_id,
            ])
            ->values();

        return Inertia::render('ParentChildSwitcherPage', [
            'children' => $children,
            'activeChildId' => $activeChildId ?? null,
        ]);
    }

    public function switch(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user && $user->isParent(), 403);
        
        $athleteId = $request->input('athlete_id');
        $athlete = Athlete::findOrFail($athleteId);

        abort_unless($athlete->parent_id && $user->children()->where('athletes.athlete_id', $athlete->athlete_id)->exists(), 403);

        $request->session()->put('active_child_id', $athlete->athlete_id);

        return back();
    }

    public function switchAthlete(Request $request, Athlete $athlete): RedirectResponse
    {
        $request->merge(['athlete_id' => $athlete->athlete_id]);

        return $this->switch($request);
    }

    public function clear(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->isParent(), 403);

        $request->session()->forget('active_child_id');

        return back();
    }
}
