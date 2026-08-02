<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class AdminPageController extends Controller
{
    public function __invoke(): Response
    {
        abort_unless(request()->user()?->isAdmin(), 403);

        $users = User::query()
            ->select(['id', 'name', 'email', 'role', 'account_status', 'created_at', 'deleted_at'])
            ->withTrashed()
            ->with([
                'roleAssignments:id,user_id,role',
                'athleteProfile' => fn ($query) => $query
                    ->select(['athlete_id', 'id', 'branch_id'])
                    ->with('branch:branch_id,branch_name'),
                'coachProfile:coach_id,id',
                'parentProfile' => fn ($query) => $query
                    ->select(['parent_id', 'id'])
                    ->with([
                        'athletes' => fn ($athletes) => $athletes
                            ->select(['athlete_id', 'parent_id', 'branch_id'])
                            ->with('branch:branch_id,branch_name'),
                    ]),
            ])
            ->latest('id')
            ->get();

        return Inertia::render('AdminPage', [
            'users' => $users->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name ?? 'Unnamed user',
                'email' => $user->email,
                'role' => $user->role,
                'roles' => $user->assignedRoles(),
                'branch' => $this->branchLabel($user),
                'status' => $user->account_status ?? User::ACCOUNT_STATUS_ACTIVE,
                'createdAt' => $user->created_at?->format('d M Y') ?? '-',
                'deletedAt' => $user->deleted_at?->format('d M Y H:i:s'),
            ])->values(),
        ]);
    }

    private function branchLabel(User $user): string
    {
        if ($user->hasRole('athlete')) {
            return $user->athleteProfile?->branch?->branch_name ?? 'Unassigned';
        }

        if ($user->hasRole('parent')) {
            return $user->parentProfile?->athletes
                ?->pluck('branch.branch_name')
                ->filter()
                ->unique()
                ->implode(', ') ?: 'Linked by child';
        }

        if ($user->hasRole('coach')) {
            return $user->coachProfile ? 'Coaching team' : 'Unassigned';
        }

        return 'Head Office';
    }
}
