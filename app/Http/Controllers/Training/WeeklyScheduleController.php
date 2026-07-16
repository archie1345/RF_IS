<?php

namespace App\Http\Controllers\Training;

use App\Actions\Sessions\GenerateWeeklyTrainingSessions;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Training\Concerns\BuildsTrainingPayloads;
use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Group;
use App\Models\WeeklyTrainingSchedule;
use App\Support\ActivityLogger;
use App\Support\Domain\BeltRank;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WeeklyScheduleController extends Controller
{
    use BuildsTrainingPayloads;

    public function __construct(private readonly GenerateWeeklyTrainingSessions $sessionGenerator) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $canManageSchedule = (bool) ($user?->isAdmin() || $user?->isCoach());
        $coachId = $user?->coachProfile?->coach_id;
        $weekStart = $request->date('from')?->startOfDay() ?? now()->startOfWeek();
        $weekEnd = $request->date('to')?->endOfDay() ?? $weekStart->copy()->endOfWeek();
        $weeklySchedulesQuery = $this->weeklyScheduleQuery($weekStart, $weekEnd);
        $athlete = $user?->isAthlete() ? $user->athleteProfile : null;

        if ($athlete) {
            $weeklySchedulesQuery
                ->where('is_active', true)
                ->where('branch_id', $athlete->branch_id)
                ->where(function ($query) use ($athlete): void {
                    $query->whereNull('dedicated_athlete_id')
                        ->orWhere('dedicated_athlete_id', $athlete->athlete_id);
                });
        }

        $weeklySchedules = $weeklySchedulesQuery->get();

        if ($athlete) {
            $weeklySchedules = $weeklySchedules
                ->filter(fn (WeeklyTrainingSchedule $schedule) => $this->athleteCanJoinSchedule($athlete, $schedule))
                ->values();
        }

        $branches = Branch::query()->orderBy('branch_name')->get();
        $groups = Group::query()->orderBy('group_name')->get();

        return Inertia::render('WeeklySchedulePage', [
            'title' => 'Jadwal Latihan',
            'subtitle' => 'Jadwal latihan rutin',
            'canManageSchedule' => $canManageSchedule,
            'currentCoachId' => $coachId,
            'weekRange' => ['from' => $weekStart->toDateString(), 'to' => $weekEnd->toDateString()],
            'weeklySchedules' => $this->weeklySchedulePayload($request, $weeklySchedules),
            'branchOptions' => $branches->map(fn (Branch $branch) => ['value' => $branch->branch_id, 'label' => $branch->branch_name])->values(),
            'groupOptions' => $groups->map(fn (Group $group) => ['value' => $group->group_id, 'label' => $group->group_name])->values(),
            'coachOptions' => $this->coachOptions(),
            'athleteOptions' => $this->authorizedAthleteQuery($request)->with('user:id,name')->orderBy('id')->get()->map(fn (Athlete $athlete) => ['value' => $athlete->athlete_id, 'label' => $athlete->user?->name ?? ('Atlet #'.$athlete->athlete_id)])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeScheduleWrite($request);
        $validated = $this->normalizeScheduleForUser($request, $this->validatedSchedule($request));
        if ($error = $this->privateAthleteError($request, $validated)) {
            return back()->withErrors(['dedicated_athlete_id' => $error])->withInput();
        }

        if ($this->scheduleWindowExists($validated)) {
            return back()->withErrors(['start_time' => 'Jadwal dengan slot, tipe sesi, dan kelas/atlet yang sama sudah ada. Gunakan judul/tipe berbeda atau ubah waktunya.'])->withInput();
        }

        try {
            $schedule = WeeklyTrainingSchedule::query()->create($validated);
        } catch (UniqueConstraintViolationException) {
            return back()->withErrors(['start_time' => 'Jadwal dengan slot, tipe sesi, kelas/atlet, dan judul yang sama sudah ada.'])->withInput();
        }
        $result = $this->sessionGenerator->handle(now()->startOfDay(), now()->copy()->addDays(14)->endOfDay(), [$schedule->weekly_training_schedule_id]);
        ActivityLogger::log($request, 'training_schedule.created', 'training', 'Created weekly training schedule', $schedule, ['title' => $validated['title'], 'auto_created_sessions' => $result['created']]);

        return back()->with('status', "Jadwal mingguan disimpan. Auto-created {$result['created']} sesi latihan untuk 14 hari ke depan; skipped {$result['skipped']} duplikat.");
    }

    public function update(Request $request, WeeklyTrainingSchedule $schedule): RedirectResponse
    {
        abort_unless($this->canManageSchedule($request, $schedule), 403);
        $validated = $this->normalizeScheduleForUser($request, $this->validatedSchedule($request));
        if ($error = $this->privateAthleteError($request, $validated)) {
            return back()->withErrors(['dedicated_athlete_id' => $error])->withInput();
        }

        if ($this->scheduleWindowExists($validated, $schedule->weekly_training_schedule_id)) {
            return back()->withErrors(['start_time' => 'Jadwal dengan slot, tipe sesi, dan kelas/atlet yang sama sudah ada. Gunakan judul/tipe berbeda atau ubah waktunya.'])->withInput();
        }

        try {
            $schedule->update($validated);
        } catch (UniqueConstraintViolationException) {
            return back()->withErrors(['start_time' => 'Jadwal dengan slot, tipe sesi, kelas/atlet, dan judul yang sama sudah ada.'])->withInput();
        }
        $result = $this->sessionGenerator->handle(now()->startOfDay(), now()->copy()->addDays(14)->endOfDay(), [$schedule->weekly_training_schedule_id]);
        ActivityLogger::log($request, 'training_schedule.updated', 'training', 'Updated weekly training schedule', $schedule, ['title' => $schedule->title, 'auto_created_sessions' => $result['created']]);

        return back()->with('status', "Jadwal mingguan diperbarui. Auto-created {$result['created']} sesi latihan untuk 14 hari ke depan; skipped {$result['skipped']} duplikat.");
    }

    public function destroy(Request $request, WeeklyTrainingSchedule $schedule): RedirectResponse
    {
        abort_unless($this->canManageSchedule($request, $schedule), 403);

        if ($schedule->trainingSessions()->exists()) {
            $schedule->update(['is_active' => false]);

            return back()->with('status', 'Jadwal sudah punya sesi latihan, jadi dinonaktifkan, bukan dihapus.');
        }

        $schedule->delete();

        return back()->with('status', 'Jadwal mingguan dihapus.');
    }

    public function generate(Request $request, GenerateWeeklyTrainingSessions $generator): RedirectResponse
    {
        $this->authorizeScheduleWrite($request);

        $from = $request->date('from')?->startOfDay() ?? now()->startOfWeek();
        $to = $request->date('to')?->endOfDay() ?? $from->copy()->endOfWeek();
        $result = $generator->handle($from, $to);

        return back()->with('status', "Generated {$result['created']} sesi latihan. Skipped {$result['skipped']} duplikat.");
    }

    private function validatedSchedule(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'branch_id' => ['required', 'exists:branches,branch_id'],
            'group_id' => ['nullable', 'exists:class_groups,group_id'],
            'dedicated_athlete_id' => ['nullable', 'exists:athletes,athlete_id'],
            'coach_id' => ['nullable', 'exists:coaches,coach_id'],
            'session_type' => ['required', 'string', 'max:40'],
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'location' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);
    }

    private function normalizeScheduleForUser(Request $request, array $validated): array
    {
        if ($request->user()?->isCoach() && ! $request->user()?->isAdmin()) {
            $validated['coach_id'] = $request->user()?->coachProfile?->coach_id;
        }

        $validated['session_type'] = str($validated['session_type'] ?? 'reguler')->lower()->slug('_')->toString();
        if ($validated['session_type'] === 'private') {
            $validated['group_id'] = null;
        } else {
            $validated['dedicated_athlete_id'] = null;
        }

        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);

        return $validated;
    }

    private function privateAthleteError(Request $request, array $validated): ?string
    {
        if (($validated['session_type'] ?? null) !== 'private') {
            return null;
        }

        if (blank($validated['dedicated_athlete_id'] ?? null)) {
            return 'Pilih atlet untuk sesi private/dedicated.';
        }

        $isAuthorized = $this->authorizedAthleteQuery($request)
            ->where('athlete_id', $validated['dedicated_athlete_id'])
            ->exists();

        return $isAuthorized ? null : 'Atlet tidak tersedia untuk cakupan cabang/grup Anda.';
    }

    private function authorizedAthleteQuery(Request $request)
    {
        $query = Athlete::query();
        $user = $request->user();

        if (! $user || $user->isAdmin()) {
            return $query;
        }

        if ($user->isCoach()) {
            $coachId = $user->coachProfile?->coach_id;
            $managedGroups = Group::query()
                ->where('coach_id', $coachId)
                ->get(['group_id', 'branch_id']);
            $groupIds = $managedGroups->pluck('group_id')->filter()->values();
            $branchIds = $managedGroups->pluck('branch_id')->filter()->unique()->values();

            if ($groupIds->isEmpty() && $branchIds->isEmpty()) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where(function ($query) use ($groupIds, $branchIds): void {
                if ($groupIds->isNotEmpty()) {
                    $query->whereIn('group_id', $groupIds);
                }

                if ($branchIds->isNotEmpty()) {
                    $groupIds->isNotEmpty()
                        ? $query->orWhereIn('branch_id', $branchIds)
                        : $query->whereIn('branch_id', $branchIds);
                }
            });
        }

        return $query->whereRaw('1 = 0');
    }

    private function athleteCanJoinSchedule(Athlete $athlete, WeeklyTrainingSchedule $schedule): bool
    {
        if ((string) $athlete->branch_id !== (string) $schedule->branch_id) {
            return false;
        }

        if ($schedule->dedicated_athlete_id !== null) {
            return (string) $athlete->athlete_id === (string) $schedule->dedicated_athlete_id;
        }

        if ($schedule->group_id === null) {
            return true;
        }

        if ((string) $athlete->group_id === (string) $schedule->group_id) {
            return true;
        }

        return BeltRank::eligible($athlete->geup, $schedule->group?->min_belt);
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

    private function authorizeScheduleWrite(Request $request): void
    {
        abort_unless($request->user()?->isAdmin() || $request->user()?->isCoach(), 403);
    }
}
