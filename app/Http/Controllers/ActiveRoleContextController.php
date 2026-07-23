<?php

namespace App\Http\Controllers;

use App\Services\ActiveRoleContextService;
use App\Support\RoleResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActiveRoleContextController extends Controller
{
    public function __construct(private readonly ActiveRoleContextService $activeRoleContext) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(RoleResolver::ROLES)],
        ]);

        $activeRole = $this->activeRoleContext->switchRole($request, $validated['role']);

        return redirect()
            ->route('dashboard')
            ->with('status', 'Active role changed to '.str($activeRole)->headline().'.');
    }
}
