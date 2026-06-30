<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
use App\Services\ParentChildContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ParentChildContextController extends Controller
{
    public function __construct(private readonly ParentChildContextService $childContext)
    {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user && $user->isParent(), 403);

        $activeChildId = $this->childContext->activeChildId($request);
        $children = $this->childContext->childOptionsFor($user, $activeChildId);

        return Inertia::render('ParentChildSwitcherPage', [
            'children' => $children,
            'activeChildId' => $activeChildId ?? null,
        ]);
    }

    public function switch(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user && $user->isParent(), 403);
        
        $athleteId = (string) $request->input('athlete_id');
        $this->childContext->setActiveChild($request, $athleteId);

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

        $this->childContext->clearActiveChild($request);

        return back();
    }
}
