<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Coach;
use App\Models\Group;
use App\Models\Parents;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminManagementController extends Controller
{
    public function index(): Response
    {
        $hasAccountStatus = Schema::hasColumn('users', 'account_status');

        $users = User::query()
            ->with([
                'athleteProfile.branch:branch_id,branch_name',
                'coachProfile',
                'parentProfile.athletes.branch:branch_id,branch_name',
            ])
            ->latest('id')
            ->get();

        return Inertia::render('Admin/Index', [
            'users' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name ?? 'Unnamed user',
                'email' => $user->email,
                'role' => $user->role,
                'branch' => $this->branchLabel($user),
                'status' => $hasAccountStatus ? ($user->account_status ?? 'active') : 'active',
                'createdAt' => $user->created_at?->format('d M Y') ?? '-',
            ])->values(),
            'branches' => Branch::query()
                ->orderBy('branch_name')
                ->get()
                ->map(fn (Branch $branch) => [
                    'id' => (string) $branch->branch_id,
                    'name' => $branch->branch_name,
                    'location' => $branch->location,
                ])
                ->values(),
            'groups' => Group::query()
                ->orderBy('group_name')
                ->get()
                ->map(fn (Group $group) => [
                    'id' => (string) $group->group_id,
                    'name' => $group->group_name,
                    'description' => $group->description,
                ])
                ->values(),
            'debugbar' => [
                'enabled' => class_exists(\Barryvdh\Debugbar\ServiceProvider::class),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAccount($request);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'gender' => 'MALE',
            'role' => $validated['role'],
            ...(Schema::hasColumn('users', 'account_status') ? ['account_status' => $validated['status']] : []),
        ]);

        $this->syncRoleProfile($user);
        ActivityLogger::log($request, 'admin.account.created', 'admin', 'Created user account', $user, ['role' => $user->role]);

        return redirect()->route('admin.index');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $this->validateAccount($request, $user);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            ...(! empty($validated['password']) ? ['password' => Hash::make($validated['password'])] : []),
            ...(Schema::hasColumn('users', 'account_status') ? ['account_status' => $validated['status']] : []),
        ]);

        $this->syncRoleProfile($user);
        ActivityLogger::log($request, 'admin.account.updated', 'admin', 'Updated user account', $user, ['role' => $user->role]);

        return redirect()->route('admin.index');
    }

    public function storeBranch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'location' => ['required', 'string', 'max:255'],
        ]);

        $branch = Branch::create([
            'branch_name' => $validated['name'],
            'location' => $validated['location'],
        ]);
        ActivityLogger::log($request, 'admin.branch.created', 'admin', 'Created branch', $branch, ['branch_name' => $branch->branch_name]);

        return redirect()->route('admin.index');
    }

    public function updateBranch(Request $request, Branch $branch): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'location' => ['required', 'string', 'max:255'],
        ]);

        $branch->update([
            'branch_name' => $validated['name'],
            'location' => $validated['location'],
        ]);
        ActivityLogger::log($request, 'admin.branch.updated', 'admin', 'Updated branch', $branch, ['branch_name' => $branch->branch_name]);

        return redirect()->route('admin.index');
    }

    public function destroyBranch(Branch $branch): RedirectResponse
    {
        ActivityLogger::log($request, 'admin.branch.deleted', 'admin', 'Deleted branch', $branch, ['branch_name' => $branch->branch_name]);
        $branch->delete();

        return redirect()->route('admin.index');
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $group = Group::create([
            'group_name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);
        ActivityLogger::log($request, 'admin.group.created', 'admin', 'Created group', $group, ['group_name' => $group->group_name]);

        return redirect()->route('admin.index');
    }

    public function updateGroup(Request $request, Group $group): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $group->update([
            'group_name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);
        ActivityLogger::log($request, 'admin.group.updated', 'admin', 'Updated group', $group, ['group_name' => $group->group_name]);

        return redirect()->route('admin.index');
    }

    public function destroyGroup(Group $group): RedirectResponse
    {
        ActivityLogger::log($request, 'admin.group.deleted', 'admin', 'Deleted group', $group, ['group_name' => $group->group_name]);
        $group->delete();

        return redirect()->route('admin.index');
    }

    private function validateAccount(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::in(['admin', 'coach', 'parent', 'athlete'])],
            'status' => ['required', Rule::in(['active', 'invited', 'suspended'])],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
        ]);
    }

    private function branchLabel(User $user): string
    {
        if ($user->role === 'athlete') {
            return $user->athleteProfile?->branch?->branch_name ?? 'Unassigned';
        }

        if ($user->role === 'parent') {
            return $user->parentProfile?->athletes
                ?->pluck('branch.branch_name')
                ->filter()
                ->unique()
                ->implode(', ') ?: 'Linked by child';
        }

        if ($user->role === 'coach') {
            return $user->coachProfile ? 'Coaching team' : 'Unassigned';
        }

        return 'Head Office';
    }

    private function syncRoleProfile(User $user): void
    {
        if ($user->role === 'parent') {
            Parents::firstOrCreate(
                ['id' => $user->id],
                ['relation' => 'guardian'],
            );
        }

        if ($user->role === 'coach') {
            Coach::firstOrCreate(
                ['id' => $user->id],
                ['status' => 'active'],
            );
        }
    }
}
