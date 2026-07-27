<?php

namespace App\Services;

use App\Models\Athlete;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ParentChildContextService
{
    public function childrenFor(?User $user): EloquentCollection
    {
        if (! $user || ! $user->isParent()) {
            return new EloquentCollection;
        }

        return $user->children()
            ->with(['user:id,name,email', 'branch:branch_id,branch_name', 'group:group_id,group_name'])
            ->orderBy('athlete_id')
            ->get();
    }

    public function childOptionsFor(?User $user, ?string $activeChildId = null): Collection
    {
        return $this->childrenFor($user)
            ->map(fn (Athlete $athlete) => [
                'athlete_id' => $athlete->athlete_id,
                'user_id' => $athlete->id,
                'name' => $athlete->user?->name ?? 'Unknown athlete',
                'email' => $athlete->user?->email ?? '-',
                'branch' => $athlete->branch?->branch_name ?? 'Unassigned',
                'group' => $athlete->group?->group_name ?? 'Unassigned',
                'is_active' => false,
            ])
            ->values();
    }

    public function sharedChildrenFor(?User $user): Collection
    {
        return $this->childrenFor($user)
            ->map(fn (Athlete $athlete) => [
                'athlete_id' => $athlete->athlete_id,
                'user_id' => $athlete->id,
                'name' => $athlete->user?->name ?? 'Unknown athlete',
            ])
            ->values();
    }

    /**
     * Kept for backwards compatibility with old links. Parent list pages no
     * longer use this value to reduce their records to one child.
     */
    public function activeChildId(Request $request, bool $defaultToFirst = false): ?string
    {
        $user = $request->user();
        if (! $user || ! $user->isParent()) {
            return null;
        }

        $children = $this->childrenFor($user);
        $activeChildId = $request->session()->get('active_child_id');

        if ($activeChildId !== null && $children->contains(
            fn (Athlete $athlete) => (string) $athlete->athlete_id === (string) $activeChildId,
        )) {
            return (string) $activeChildId;
        }

        $request->session()->forget('active_child_id');

        return null;
    }

    public function activeChildFor(Request $request, bool $defaultToFirst = false): ?array
    {
        $activeChildId = $this->activeChildId($request, false);
        if ($activeChildId === null) {
            return null;
        }

        return $this->sharedChildrenFor($request->user())
            ->first(fn (array $child) => (string) $child['athlete_id'] === $activeChildId);
    }

    /** @return array<int, string> */
    public function visibleChildAthleteIds(Request $request, bool $respectActiveChild = true, bool $defaultToFirst = false): array
    {
        $user = $request->user();
        if (! $user || ! $user->isParent()) {
            return [];
        }

        return $this->childrenFor($user)
            ->pluck('athlete_id')
            ->map(fn ($id): string => (string) $id)
            ->values()
            ->all();
    }

    /** @return array<int, int> */
    public function visibleChildUserIds(Request $request, bool $respectActiveChild = true, bool $defaultToFirst = false): array
    {
        $user = $request->user();
        if (! $user || ! $user->isParent()) {
            return [];
        }

        return $this->childrenFor($user)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    public function setActiveChild(Request $request, string $athleteId): Athlete
    {
        $user = $request->user();
        abort_unless($user && $user->isParent(), 403);

        $athlete = Athlete::query()->findOrFail($athleteId);
        abort_unless($this->belongsToParent($user, $athlete), 403);

        $request->session()->put('active_child_id', $athlete->athlete_id);

        return $athlete;
    }

    public function clearActiveChild(Request $request): void
    {
        $request->session()->forget('active_child_id');
    }

    public function belongsToParent(User $user, Athlete $athlete): bool
    {
        return $user->isParent()
            && $athlete->parent_id !== null
            && $user->children()->where('athletes.athlete_id', $athlete->athlete_id)->exists();
    }
}
