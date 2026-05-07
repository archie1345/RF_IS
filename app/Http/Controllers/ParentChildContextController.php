<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ParentChildContextController extends Controller
{
    public function switch(Request $request, Athlete $athlete): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user && $user->isParent(), 403);
        abort_unless($athlete->parent_id && $user->parentProfile?->parent_id === $athlete->parent_id, 403);

        $request->session()->put('active_child_id', $athlete->athlete_id);

        return back();
    }

    public function clear(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->isParent(), 403);

        $request->session()->forget('active_child_id');

        return back();
    }
}

