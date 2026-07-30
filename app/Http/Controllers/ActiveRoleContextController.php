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
            'redirect_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $activeRole = $this->activeRoleContext->switchRole($request, $validated['role']);
        $redirectTo = $validated['redirect_to'] ?? null;
        $redirect = is_string($redirectTo)
            && str_starts_with($redirectTo, '/')
            && ! str_starts_with($redirectTo, '//')
                ? redirect()->to($redirectTo)
                : redirect()->route('dashboard');

        return $redirect->with('status', 'Active role changed to '.str($activeRole)->headline().'.');
    }
}
