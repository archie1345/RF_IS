<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Training\Concerns\BuildsTrainingPayloads;
use App\Models\Branch;
use App\Models\Group;
use App\Models\TrainingSession;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class TrainingManagementController extends Controller
{
    use BuildsTrainingPayloads;

    public function index(Request $request): Response
    {
        $weekStart = $request->date('from')?->startOfDay() ?? now()->startOfWeek();
        $weekEnd = $request->date('to')?->endOfDay() ?? $weekStart->copy()->endOfWeek();
        $weeklySchedules = $this->weeklyScheduleQuery($weekStart, $weekEnd)->get();
        $branches = Branch::query()->withCount(['groups', 'athletes'])->orderBy('branch_name')->get();
        $groups = Group::query()->with(['branch', 'coach.user'])->withCount('athletes')->orderBy('group_name')->get();

        $sessions = TrainingSession::query()
            ->with(['branch', 'group', 'primaryCoach.user', 'weeklyTrainingSchedule'])
            ->whereBetween('session_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->where('status', '!=', 'CANCELED')
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();

        return Inertia::render('TrainingManagementPage', [
            'title' => 'Manajemen Latihan',
            'subtitle' => 'Ringkasan training flow. Lokasi, Kelas, dan Jadwal Latihan sudah dipisah ke halaman khusus.',
            'weekRange' => ['from' => $weekStart->toDateString(), 'to' => $weekEnd->toDateString()],
            'branches' => $branches->map(fn (Branch $branch) => $this->branchPayload($branch))->values(),
            'groups' => $this->groupPayload($groups, $weeklySchedules),
            'weeklySchedules' => $this->weeklySchedulePayload($request, $weeklySchedules),
            'sessions' => $sessions->map(fn (TrainingSession $session) => [
                'id' => $session->training_session_id,
                'weekly_training_schedule_id' => $session->weekly_training_schedule_id,
                'title' => $session->title,
                'date' => optional($session->session_date)->format('Y-m-d'),
                'day_label' => $session->session_date ? $this->dayName(Carbon::parse((string) $session->session_date)->isoWeekday()) : '-',
                'time' => substr((string) $session->start_time, 0, 5).' - '.substr((string) $session->end_time, 0, 5),
                'branch' => $session->branch?->branch_name ?? 'Belum ada lokasi',
                'group' => $session->group?->group_name ?? 'All groups',
                'coach' => $session->primaryCoach?->user?->name ?? 'Belum ada coach',
                'status' => $session->status,
            ])->values(),
        ]);
    }
}
