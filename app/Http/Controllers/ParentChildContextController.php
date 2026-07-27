<?php

namespace App\Http\Controllers;

use App\Actions\ParentChild\ClearActiveChild;
use App\Actions\ParentChild\SwitchActiveChild;
use App\Http\Requests\ParentChild\SwitchActiveChildRequest;
use App\Models\Athlete;
use App\Services\ParentChildContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ParentChildContextController extends Controller
{
    public function __construct(
        private readonly ParentChildContextService $childContext,
        private readonly SwitchActiveChild $switchActiveChild,
        private readonly ClearActiveChild $clearActiveChild,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user && $user->isParent(), 403);

        return Inertia::render('ParentChildSwitcherPage', [
            'children' => $this->childContext->childOptionsFor($user),
        ]);
    }

    /**
     * Legacy context endpoints remain available for old bookmarked actions, but
     * all parent-facing list pages now intentionally show every linked child.
     */
    public function switch(SwitchActiveChildRequest $request): RedirectResponse
    {
        $this->switchActiveChild->handle($request, (string) $request->validated('athlete_id'));

        return back();
    }

    public function switchAthlete(Request $request, Athlete $athlete): RedirectResponse
    {
        $this->switchActiveChild->handle($request, (string) $athlete->athlete_id);

        return back();
    }

    public function clear(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->isParent(), 403);

        $this->clearActiveChild->handle($request);

        return back();
    }
}
