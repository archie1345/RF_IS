<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Training\Concerns\BuildsTrainingPayloads;
use App\Models\Branch;
use App\Models\Group;
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
            ->with(['branch', 'coach.user', 'athletes.user', 'athletes.branch'])
            ->withCount('athletes')
            ->orderBy('group_name')
            ->get();
        $branches = Branch::query()->where('is_active', true)->orderBy('branch_name')->get();

        return Inertia::render('AdminClassesPage', [
            'title' => 'Kelas Latihan',
            'subtitle' => 'Master data kelas. Jadwal mingguan otomatis sinkron dari data kelas.',
            'classes' => $this->groupPayload($groups, $weeklySchedules),
            'branchOptions' => $branches->map(fn (Branch $branch) => ['value' => $branch->branch_id, 'label' => $branch->branch_name])->values(),
            'coachOptions' => $this->coachOptions(),
            'beltOptions' => $this->beltOptions(),
        ]);
    }
}
