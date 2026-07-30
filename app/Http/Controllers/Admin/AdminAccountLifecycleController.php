<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\ResendUserInvitation;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminAccountSafetyService;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminAccountLifecycleController extends Controller
{
    public function __construct(private readonly AdminAccountSafetyService $accountSafety) {}

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor?->isAdmin(), 403);

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                User::ACCOUNT_STATUS_ACTIVE,
                User::ACCOUNT_STATUS_SUSPENDED,
            ])],
        ]);

        if ($user->isInvited()) {
            throw ValidationException::withMessages([
                'status' => 'Invited accounts become active only after the invitation is accepted.',
            ]);
        }

        $nextStatus = $validated['status'];
        $this->accountSafety->ensureUpdateIsSafe($actor, $user, $user->assignedRoles(), $nextStatus);
        $previousStatus = $user->account_status;
        $user->update(['account_status' => $nextStatus]);

        ActivityLogger::log(
            $request,
            'admin.account.status_updated',
            'admin',
            'Updated account active state',
            $user,
            [
                'previous_status' => $previousStatus,
                'next_status' => $nextStatus,
            ],
        );

        return back()->with(
            'status',
            $nextStatus === User::ACCOUNT_STATUS_ACTIVE
                ? 'Account activated.'
                : 'Account marked as not active.',
        );
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor?->isAdmin(), 403);
        $this->accountSafety->ensureDeletionIsSafe($actor, $user);

        $user->delete();
        ActivityLogger::log(
            $request,
            'admin.account.deleted',
            'admin',
            'Soft deleted user account',
            $user,
            ['user_id' => $user->id],
        );

        return redirect()->route('admin.index');
    }

    public function restore(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $user = User::withTrashed()->findOrFail($id);

        $user->restore();
        ActivityLogger::log(
            $request,
            'admin.account.restored',
            'admin',
            'Restored soft deleted user account',
            $user,
            ['user_id' => $user->id],
        );

        return redirect()->route('admin.index');
    }

    public function resendInvitation(
        Request $request,
        User $user,
        ResendUserInvitation $resendUserInvitation,
    ): RedirectResponse {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($user->isInvited(), 422, 'Only invited accounts can receive an invitation email.');

        $resendUserInvitation->handle($user);
        ActivityLogger::log(
            $request,
            'admin.account.invitation_resent',
            'admin',
            'Resent user invitation',
            $user,
            ['user_id' => $user->id],
        );

        return back()->with('status', 'Invitation email sent.');
    }

    public function forceDelete(Request $request, int $id): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor?->isAdmin(), 403);
        $user = User::withTrashed()->findOrFail($id);
        $this->accountSafety->ensureDeletionIsSafe($actor, $user);

        if (! $user->trashed()) {
            return back()->withErrors([
                'account' => 'You can only permanently delete an account that has already been soft deleted.',
            ]);
        }

        $userId = $user->id;
        $user->forceDelete();
        ActivityLogger::log(
            $request,
            'admin.account.force_deleted',
            'admin',
            'Permanently deleted user account',
            null,
            ['user_id' => $userId],
        );

        return redirect()->route('admin.index');
    }
}
