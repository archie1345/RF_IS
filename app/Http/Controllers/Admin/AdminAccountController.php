<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\CreateUserInvitation;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserRoleManagementService;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminAccountController extends Controller
{
    public function __construct(private readonly UserRoleManagementService $roleManagement) {}

    public function store(Request $request, CreateUserInvitation $createUserInvitation): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $user = $this->roleManagement->createAccount($this->validateAccount($request));

        if ($user->isInvited()) {
            $createUserInvitation->handle($user);
        }

        ActivityLogger::log(
            $request,
            'admin.account.created',
            'admin',
            'Created user account',
            $user,
            [
                'primary_role' => $user->role,
                'roles' => $user->assignedRoles(),
            ],
        );

        return back();
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $this->validateAccount($request, $user);
        $this->validateAccountStatusTransition($user, $validated['status']);
        $user = $this->roleManagement->updateAccount($user, $validated);

        ActivityLogger::log(
            $request,
            'admin.account.updated',
            'admin',
            'Updated user account',
            $user,
            [
                'primary_role' => $user->role,
                'roles' => $user->assignedRoles(),
            ],
        );

        return redirect()->route('admin.index');
    }

    private function validateAccount(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'distinct', Rule::in(['admin', 'coach', 'parent', 'athlete'])],
            'status' => ['required', Rule::in([
                User::ACCOUNT_STATUS_ACTIVE,
                User::ACCOUNT_STATUS_INVITED,
                User::ACCOUNT_STATUS_SUSPENDED,
            ])],
            'password' => [
                $user || $request->input('status') === User::ACCOUNT_STATUS_INVITED ? 'nullable' : 'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);
    }

    private function validateAccountStatusTransition(User $user, string $nextStatus): void
    {
        $currentStatus = $user->account_status ?? User::ACCOUNT_STATUS_ACTIVE;

        if ($currentStatus === $nextStatus) {
            return;
        }

        $allowed = [
            User::ACCOUNT_STATUS_ACTIVE => [User::ACCOUNT_STATUS_SUSPENDED],
            User::ACCOUNT_STATUS_SUSPENDED => [User::ACCOUNT_STATUS_ACTIVE],
            User::ACCOUNT_STATUS_INVITED => [],
        ];

        if (! in_array($nextStatus, $allowed[$currentStatus] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => 'This account status transition is not supported. Invited accounts become active only by accepting an invitation.',
            ]);
        }
    }
}
