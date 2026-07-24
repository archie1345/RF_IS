<?php

namespace App\Http\Controllers\Training;

use App\Actions\Sessions\GenerateWeeklyTrainingSessions;
use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\Group;
use App\Models\WeeklyTrainingSchedule;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WeeklyScheduleController extends Controller
{
    public function __construct(private readonly GenerateWeeklyTrainingSessions $sessionGenerator) {}

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeScheduleWrite($request);
        $validated = $this->normalizeScheduleForUser($request, $this->validatedSchedule($request));

        if ($error = $this->scheduleScopeError($request, $validated)) {
            return back()->withErrors($error)->withInput();
        }

        if ($this->scheduleWindowExists($validated)) {
            return back()->withErrors([
                'start_time' => 'Jadwal dengan slot, tipe sesi, dan kelas/atlet yang sama sudah ada. Gunakan judul/tipe berbeda atau ubah waktunya.',
            ])->withInput();
        }

        try {
            [$schedule, $result] = DB::transaction(function () use ($validated): array {
                $schedule = WeeklyTrainingSchedule::query()->create($validated);
                $result = $schedule->is_active
                    ? $this->sessionGenerator->handle(
                        now()->startOfDay(),
                        now()->copy()->addDays(14)->endOfDay(),
                        [$schedule->weekly_training_schedule_id],
                    )
                    : $this->emptyGenerationResult();

                return [$schedule, $result];
            });
        } catch (UniqueConstraintViolationException) {
            return back()->withErrors([
                'start_time' => 'Jadwal dengan slot, tipe sesi, kelas/atlet, dan judul yang sama sudah ada.',
            ])->withInput();
        }

        ActivityLogger::log(
            $request,
            'training_schedule.created',
            'training',
            'Created weekly training schedule',
            $schedule,
            ['title' => $validated['title'], 'auto_created_sessions' => $result['created']],
        );

        return back()->with(
            'status',
            "Jadwal mingguan disimpan. Auto-created {$result['created']} sesi latihan; updated {$result['updated']}; removed {$result['removed']}; skipped {$result['skipped']}.",
        );
    }

    public function update(Request $request, WeeklyTrainingSchedule $schedule): RedirectResponse
    {
        abort_unless($this->canManageSchedule($request, $schedule), 403);
        $validated = $this->normalizeScheduleForUser($request, $this->validatedSchedule($request));

        if ($error = $this->scheduleScopeError($request, $validated)) {
            return back()->withErrors($error)->withInput();
        }

        if ($this->scheduleWindowExists($validated, $schedule->weekly_training_schedule_id)) {
            return back()->withErrors([
                'start_time' => 'Jadwal dengan slot, tipe sesi, dan kelas/atlet yang sama sudah ada. Gunakan judul/tipe berbeda atau ubah waktunya.',
            ])->withInput();
        }

        try {
            $result = DB::transaction(function () use ($schedule, $validated): array {
                $schedule->update($validated);

                if (! $schedule->is_active) {
                    return [
                        ...$this->emptyGenerationResult(),
                        'removed' => $this->sessionGenerator->removeFutureSessionsForSchedule($schedule),
                    ];
                }

                return $this->sessionGenerator->handle(
                    now()->startOfDay(),
                    now()->copy()->addDays(14)->endOfDay(),
                    [$schedule->weekly_training_schedule_id],
                );
            });
        } catch (UniqueConstraintViolationException) {
            return back()->withErrors([
                'start_time' => 'Jadwal dengan slot, tipe sesi, kelas/atlet, dan judul yang sama sudah ada.',
            ])->withInput();
        }

        ActivityLogger::log(
            $request,
            'training_schedule.updated',
            'training',
            'Updated weekly training schedule',
            $schedule,
            [
                'title' => $schedule->title,
                'auto_created_sessions' => $result['created'],
                'updated_sessions' => $result['updated'],
                'removed_sessions' => $result['removed'],
            ],
        );

        return back()->with(
            'status',
            "Jadwal mingguan diperbarui. Created {$result['created']}; updated {$result['updated']}; removed {$result['removed']}; skipped {$result['skipped']}.",
        );
    }

    public function destroy(Request $request, WeeklyTrainingSchedule $schedule): RedirectResponse
    {
        abort_unless($this->canManageSchedule($request, $schedule), 403);

        if ($schedule->trainingSessions()->withTrashed()->exists()) {
            $removed = DB::transaction(function () use ($schedule): int {
                $schedule->update(['is_active' => false]);

                return $this->sessionGenerator->removeFutureSessionsForSchedule($schedule);
            });

            ActivityLogger::log(
                $request,
                'training_schedule.deactivated',
                'training',
                'Deactivated weekly training schedule with session history',
                $schedule,
                ['removed_future_sessions' => $removed],
            );

            return back()->with(
                'status',
                "Jadwal memiliki riwayat sehingga dinonaktifkan. {$removed} sesi mendatang dihapus.",
            );
        }

        ActivityLogger::log(
            $request,
            'training_schedule.deleted',
            'training',
            'Deleted unused weekly training schedule',
            $schedule,
        );
        $schedule->delete();

        return back()->with('status', 'Jadwal mingguan dihapus.');
    }

    public function generate(Request $request): RedirectResponse
    {
        $this->authorizeScheduleWrite($request);
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = filled($validated['from'] ?? null)
            ? now()->parse($validated['from'])->startOfDay()
            : now()->startOfWeek();
        $to = filled($validated['to'] ?? null)
            ? now()->parse($validated['to'])->endOfDay()
            : $from->copy()->endOfWeek();

        if ($from->diffInDays($to) > 90) {
            return back()->withErrors(['to' => 'Rentang pembuatan sesi maksimal 90 hari.']);
        }

        $scheduleIds = null;
        $user = $request->user();
        if ($user?->isCoach() && ! $user->isAdmin()) {
            $coachId = $user->coachProfile?->coach_id;
            $scheduleIds = WeeklyTrainingSchedule::query()
                ->where(function (Builder $query) use ($coachId): void {
                    $query->where('coach_id', $coachId)
                        ->orWhereHas('group', fn (Builder $group) => $group->assignedToCoach($coachId));
                })
                ->pluck('weekly_training_schedule_id')
                ->all();
        }

        $result = $this->sessionGenerator->handle($from, $to, $scheduleIds);

        return back()->with(
            'status',
            "Generated {$result['created']} sesi latihan; updated {$result['updated']}; removed {$result['removed']}; skipped {$result['skipped']}.",
        );
    }

    private function validatedSchedule(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'branch_id' => [
                'required',
                Rule::exists('branches', 'branch_id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'group_id' => [
                'nullable',
                Rule::exists('class_groups', 'group_id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at')),
            ],
            'dedicated_athlete_id' => ['nullable', 'exists:athletes,athlete_id'],
            'coach_id' => [
                'nullable',
                Rule::exists('coaches', 'coach_id')->where(fn ($query) => $query->where('status', 'active')->whereNull('deleted_at')),
            ],
            'session_type' => ['required', Rule::in(['reguler', 'private'])],
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'location' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function normalizeScheduleForUser(Request $request, array $validated): array
    {
        if ($request->user()?->isCoach() && ! $request->user()?->isAdmin()) {
            $validated['coach_id'] = $request->user()?->coachProfile?->coach_id;
        }

        if (($validated['session_type'] ?? 'reguler') === 'private') {
            $validated['group_id'] = null;
        } else {
            $validated['dedicated_athlete_id'] = null;
        }

        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);

        return $validated;
    }

    private function scheduleScopeError(Request $request, array $validated): ?array
    {
        $user = $request->user();
        $group = filled($validated['group_id'] ?? null)
            ? Group::query()->find($validated['group_id'])
            : null;

        if ($group && (string) $group->branch_id !== (string) $validated['branch_id']) {
            return ['group_id' => 'Kelas yang dipilih harus berada pada cabang yang sama.'];
        }

        if ($user?->isCoach() && ! $user->isAdmin()) {
            $coachId = $user->coachProfile?->coach_id;
            if (! $coachId) {
                return ['coach_id' => 'Profil pelatih aktif diperlukan untuk mengelola jadwal.'];
            }

            if ($group && ! Group::query()->assignedToCoach($coachId)->whereKey($group->group_id)->exists()) {
                return ['group_id' => 'Pelatih hanya dapat menggunakan kelas yang ditugaskan kepadanya.'];
            }
        }

        if (($validated['session_type'] ?? null) === 'private') {
            if (blank($validated['dedicated_athlete_id'] ?? null)) {
                return ['dedicated_athlete_id' => 'Pilih atlet untuk sesi private/dedicated.'];
            }

            $athlete = $this->authorizedAthleteQuery($request)
                ->where('athlete_id', $validated['dedicated_athlete_id'])
                ->first();

            if (! $athlete) {
                return ['dedicated_athlete_id' => 'Atlet tidak tersedia dalam cakupan kelas pelatih.'];
            }

            if ((string) $athlete->branch_id !== (string) $validated['branch_id']) {
                return ['dedicated_athlete_id' => 'Atlet private harus berasal dari cabang yang dipilih.'];
            }
        }

        $hasCoach = filled($validated['coach_id'] ?? null)
            || ($group && collect([$group->coach_id])->merge($group->coaches()->pluck('coaches.coach_id'))->filter()->isNotEmpty());

        if (! $hasCoach) {
            return ['coach_id' => 'Jadwal aktif memerlukan setidaknya satu pelatih.'];
        }

        return null;
    }

    private function authorizedAthleteQuery(Request $request): Builder
    {
        $query = Athlete::query();
        $user = $request->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isAdmin()) {
            return $query;
        }

        if (! $user->isCoach()) {
            return $query->whereRaw('1 = 0');
        }

        $coachId = $user->coachProfile?->coach_id;
        if (! $coachId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $athletes) use ($coachId): void {
            $athletes->whereHas('group', fn (Builder $group) => $group->assignedToCoach($coachId))
                ->orWhereHas('privateGroups', fn (Builder $group) => $group->assignedToCoach($coachId));
        });
    }

    private function scheduleWindowExists(array $validated, ?int $ignoreId = null): bool
    {
        return WeeklyTrainingSchedule::query()
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('branch_id', $validated['branch_id'])
            ->where('day_of_week', $validated['day_of_week'])
            ->where('start_time', $validated['start_time'])
            ->where('end_time', $validated['end_time'])
            ->where('session_type', $validated['session_type'])
            ->where(function ($query) use ($validated): void {
                array_key_exists('group_id', $validated) && $validated['group_id'] !== null
                    ? $query->where('group_id', $validated['group_id'])
                    : $query->whereNull('group_id');
            })
            ->where(function ($query) use ($validated): void {
                array_key_exists('dedicated_athlete_id', $validated) && $validated['dedicated_athlete_id'] !== null
                    ? $query->where('dedicated_athlete_id', $validated['dedicated_athlete_id'])
                    : $query->whereNull('dedicated_athlete_id');
            })
            ->exists();
    }

    private function canManageSchedule(Request $request, WeeklyTrainingSchedule $schedule): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isCoach()) {
            return false;
        }

        $coachId = $user->coachProfile?->coach_id;
        if (! $coachId) {
            return false;
        }

        return (string) $schedule->coach_id === (string) $coachId
            || ($schedule->group_id !== null
                && Group::query()->assignedToCoach($coachId)->whereKey($schedule->group_id)->exists());
    }

    private function authorizeScheduleWrite(Request $request): void
    {
        abort_unless($request->user()?->isAdmin() || $request->user()?->isCoach(), 403);
    }

    private function emptyGenerationResult(): array
    {
        return [
            'created' => 0,
            'skipped' => 0,
            'updated' => 0,
            'removed' => 0,
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
        ];
    }
}
