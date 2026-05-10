<?php

namespace App\Http\Controllers;

use App\Models\Coach;
use App\Models\Parents;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CoachParentManagementController extends Controller
{
    public function index(): Response
    {
        $coaches = Coach::query()
            ->with('user.roleAssignments')
            ->orderByDesc('coach_id')
            ->get();

        $parents = Parents::query()
            ->with('user.roleAssignments')
            ->orderByDesc('parent_id')
            ->get();

        $coachLinkedUserIds = Coach::query()->pluck('id')->all();
        $parentLinkedUserIds = Parents::query()->pluck('id')->all();

        return Inertia::render('CoachParentManagementPage', [
            'coachRows' => $coaches->map(fn (Coach $coach) => [
                'id' => (string) $coach->coach_id,
                'name' => $coach->user?->name ?? 'Unknown user',
                'email' => $coach->user?->email ?? '-',
                'role' => $coach->user ? implode(', ', $coach->user->assignedRoles()) : '-',
                'status' => $coach->status ?? 'active',
                'specialization' => $coach->specialization ?: '-',
            ])->values(),
            'parentRows' => $parents->map(fn (Parents $parent) => [
                'id' => (string) $parent->parent_id,
                'name' => $parent->user?->name ?? 'Unknown user',
                'email' => $parent->user?->email ?? '-',
                'role' => $parent->user ? implode(', ', $parent->user->assignedRoles()) : '-',
                'relation' => $parent->relation ?? 'guardian',
                'occupation' => $parent->occupation ?: '-',
            ])->values(),
            'coachUserOptions' => User::query()
                ->with('roleAssignments')
                ->whereNotIn('id', $coachLinkedUserIds)
                ->orderBy('email')
                ->get(['id', 'name', 'email', 'role'])
                ->filter(fn (User $user) => $user->hasRole('coach') || $user->hasRole('athlete'))
                ->map(fn (User $user) => [
                    'value' => (string) $user->id,
                    'label' => sprintf('%s - %s (%s)', $user->email, $user->name ?? 'Unnamed user', implode(', ', $user->assignedRoles())),
                ])
                ->values(),
            'parentUserOptions' => User::query()
                ->with('roleAssignments')
                ->whereNotIn('id', $parentLinkedUserIds)
                ->orderBy('email')
                ->get(['id', 'name', 'email'])
                ->filter(fn (User $user) => $user->hasRole('parent'))
                ->map(fn (User $user) => [
                    'value' => (string) $user->id,
                    'label' => sprintf('%s - %s', $user->email, $user->name ?? 'Unnamed user'),
                ])
                ->values(),
        ]);
    }

    public function storeCoach(Request $request): RedirectResponse
    {
        abort(403, 'Create coach profile from Admin Panel role assignment flow only.');
    }

    public function updateCoach(Request $request, Coach $coach): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'specialization' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
        ]);

        $coach->update($validated);

        return redirect()->route('coach-parent.index');
    }

    public function storeParent(Request $request): RedirectResponse
    {
        abort(403, 'Create parent profile from Admin Panel role assignment flow only.');
    }

    public function updateParent(Request $request, Parents $parent): RedirectResponse
    {
        $validated = $request->validate([
            'relation' => ['required', Rule::in(['father', 'mother', 'guardian'])],
            'occupation' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $parent->update($validated);

        return redirect()->route('coach-parent.index');
    }
}
