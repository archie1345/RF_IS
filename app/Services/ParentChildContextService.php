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
            return new EloquentCollection();
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
                'is_active' => $activeChildId !== null && (string) $activeChildId === (string) $athlete->athlete_id,
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

    public function activeChildId(Request $request, bool $defaultToFirst = false): ?string
    {
        $user = $request->user();
        if (! $user || ! $user->isParent()) {
            return null;
        }

        $children = $this->childrenFor($user);
        $activeChildId = $request->session()->get('active_child_id');

        if ($activeChildId !== null && $children->contains(fn (Athlete $athlete) => (string) $athlete->athlete_id === (string) $activeChildId)) {
            return (string) $activeChildId;
        }

        if ($activeChildId !== null) {
            $request->session()->forget('active_child_id');
        }

        if ($defaultToFirst && $children->isNotEmpty()) {
            $firstChildId = (string) $children->first()->athlete_id;
            $request->session()->put('active_child_id', $firstChildId);

            return $firstChildId;
        }

        return null;
    }

    public function activeChildFor(Request $request, bool $defaultToFirst = false): ?array
    {
        $activeChildId = $this->activeChildId($request, $defaultToFirst);

        if ($activeChildId === null) {
            return null;
        }

        return $this->sharedChildrenFor($request->user())
            ->first(fn (array $child) => (string) $child['athlete_id'] === $activeChildId);
    }

    public function visibleChildAthleteIds(Request $request, bool $respectActiveChild = true, bool $defaultToFirst = false): array
    {
        $user = $request->user();
        if (! $user || ! $user->isParent()) {
            return [];
        }

        $children = $this->childrenFor($user)->pluck('athlete_id')->map(fn ($id) => (string) $id)->values()->all();
        $activeChildId = $respectActiveChild ? $this->activeChildId($request, $defaultToFirst) : null;

        if ($activeChildId !== null) {
            return in_array($activeChildId, $children, true) ? [$activeChildId] : [];
        }

        return $children;
    }

    public function visibleChildUserIds(Request $request, bool $respectActiveChild = true, bool $defaultToFirst = false): array
    {
        $user = $request->user();
        if (! $user || ! $user->isParent()) {
            return [];
        }

        $children = $this->childrenFor($user);
        $activeChildId = $respectActiveChild ? $this->activeChildId($request, $defaultToFirst) : null;

        return $children
            ->when($activeChildId !== null, fn ($items) => $items->filter(fn (Athlete $athlete) => (string) $athlete->athlete_id === $activeChildId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
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
