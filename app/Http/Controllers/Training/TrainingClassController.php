<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Training\Concerns\BuildsTrainingPayloads;
use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Group;
use App\Models\TrainingGroup;
use App\Models\WeeklyTrainingSchedule;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrainingClassController extends Controller
{
    use BuildsTrainingPayloads;

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $weeklySchedules = WeeklyTrainingSchedule::query()->get();
        $groups = Group::query()
            ->with(['branch', 'trainingGroup', 'coach.user', 'dedicatedAthlete.user', 'athletes.user', 'athletes.branch', 'athletes.trainingGroup'])
            ->withCount('athletes')
            ->orderBy('group_name')
            ->get();
        $branches = Branch::query()->where('is_active', true)->orderBy('branch_name')->get();
        $trainingGroups = TrainingGroup::query()
            ->where('is_active', true)
            ->whereRaw('LOWER(name) != ?', ['private'])
            ->orderBy('name')
            ->get();
        $athletes = Athlete::query()
            ->with(['user:id,name', 'branch:branch_id,branch_name', 'trainingGroup'])
            ->orderBy('athlete_id')
            ->get();

        return Inertia::render('AdminClassesPage', [
            'title' => 'Kelas Latihan',
            'subtitle' => 'Master data kelas. Grup atlet adalah kategori peserta; Private adalah tipe kelas dengan atlet khusus.',
            'classes' => $this->groupPayload($groups, $weeklySchedules),
            'branchOptions' => $branches->map(fn (Branch $branch) => ['value' => $branch->branch_id, 'label' => $branch->branch_name])->values(),
            'trainingGroupOptions' => $trainingGroups->map(fn (TrainingGroup $group) => ['value' => $group->id, 'label' => $group->name])->values(),
            'athleteOptions' => $athletes->map(fn (Athlete $athlete) => [
                'value' => $athlete->athlete_id,
                'label' => trim(($athlete->user?->name ?? 'Unknown athlete').' · '.($athlete->trainingGroup?->name ?? 'No group').' · '.($athlete->branch?->branch_name ?? 'No branch')),
            ])->values(),
            'coachOptions' => $this->coachOptions(),
            'beltOptions' => $this->beltOptions(),
        ]);
    }
}
