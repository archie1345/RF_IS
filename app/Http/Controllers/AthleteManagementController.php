<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FormatsMvpData;
use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Group;
use App\Models\Parents;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AthleteManagementController extends Controller
{
    use FormatsMvpData;

    public function index(): Response
    {
        $user = request()->user();
        $canViewSensitiveIdentifiers = (bool) $user?->isAdmin();
        $parentScopedAthleteIds = null;

        if ($user && $user->isParent()) {
            $children = $user->children()->pluck('athletes.athlete_id');
            $activeChildId = request()->session()->get('active_child_id');

            $parentScopedAthleteIds = $activeChildId
                ? $children->where(fn ($id) => (int) $id === (int) $activeChildId)
                : $children;
        }

        $athletes = Athlete::query()
            ->with(['user:id,name,email', 'branch:branch_id,branch_name', 'group:group_id,group_name', 'parent.user:id,name'])
            ->when($parentScopedAthleteIds !== null, fn ($query) => $query->whereIn('athlete_id', $parentScopedAthleteIds))
            ->latest('athlete_id')
            ->get();

        return Inertia::render('Athletes/Index', [
            'metrics' => [
                ['label' => 'Active athlete records', 'value' => (string) $athletes->count(), 'detail' => $athletes->whereNull('deleted_at')->count().' active profiles in the roster', 'tone' => 'success'],
                ['label' => 'Profiles missing parent links', 'value' => (string) $athletes->whereNull('parent_id')->count(), 'detail' => 'Records still need parent connection', 'tone' => 'warning'],
                ['label' => 'Branches represented', 'value' => (string) $athletes->pluck('branch_id')->filter()->unique()->count(), 'detail' => 'Current roster spread across active branches', 'tone' => 'info'],
            ],
            'rows' => $athletes->map(fn (Athlete $athlete) => [
                'id' => 'ATH-'.$athlete->athlete_id,
                'athlete' => $athlete->user?->name ?? 'Unknown athlete',
                'parent' => $athlete->parent?->user?->name ?? 'Not linked',
                'branch' => $athlete->branch?->branch_name ?? 'Unassigned',
                'group' => $athlete->group?->group_name ?? 'Unassigned',
                'nik' => $canViewSensitiveIdentifiers ? ($athlete->nik_encrypted ?? 'Not stored') : null,
                'bpjs' => $canViewSensitiveIdentifiers ? ($athlete->bpjs_encrypted ?? 'Not stored') : null,
                'geup' => str_replace('_', ' ', $athlete->geup),
                'status' => $this->badge($athlete->parent_id ? 'Active' : 'Awaiting parent link', $athlete->parent_id ? 'success' : 'warning'),
            ])->values(),
            'branches' => Branch::query()->orderBy('branch_name')->get(['branch_id as value', 'branch_name as label']),
            'groups' => Group::query()->orderBy('group_name')->get(['group_id as value', 'group_name as label']),
            'athletes' => $athletes
                ->map(fn (Athlete $athlete) => ['value' => $athlete->athlete_id, 'label' => $athlete->user?->name ?? 'Unknown athlete'])
                ->values(),
            'parents' => Parents::query()
                ->with('user:id,name')
                ->orderBy('parent_id')
                ->get()
                ->map(fn (Parents $parent) => ['value' => $parent->parent_id, 'label' => $parent->user?->name ?? 'Unknown parent'])
                ->values(),
            'canViewSensitiveIdentifiers' => $canViewSensitiveIdentifiers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if($request->user()?->isParent(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'gender' => ['required', Rule::in(['MALE', 'FEMALE'])],
            'bday' => ['required', 'date'],
            'phone' => ['nullable', 'string', 'max:20'],
            'height_cm' => ['required', 'numeric', 'min:0'],
            'weight_kg' => ['required', 'numeric', 'min:0'],
            'alamat' => ['nullable', 'string'],
            'geup' => ['required', Rule::in(['GEUP_1', 'GEUP_2', 'GEUP_3', 'GEUP_4', 'GEUP_5', 'GEUP_6', 'GEUP_7', 'GEUP_8', 'GEUP_9', 'GEUP_10', 'DAN'])],
            'branch_id' => ['required', 'exists:branches,branch_id'],
            'group_id' => ['required', 'exists:class_groups,group_id'],
            'parent_id' => ['nullable', 'exists:parents,parent_id'],
            'nik' => ['required', 'string', 'max:50'],
            'bpjs' => ['required', 'string', 'max:50'],
        ]);

        DB::transaction(function () use ($validated): void {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make(Str::password(16)),
                'gender' => $validated['gender'],
                'role' => 'athlete',
                'bday' => $validated['bday'],
                'phone' => $validated['phone'] ?? null,
            ]);

            Athlete::create([
                'id' => $user->id,
                'group_id' => $validated['group_id'],
                'branch_id' => $validated['branch_id'],
                'parent_id' => $validated['parent_id'] ?? null,
                'height_cm' => $validated['height_cm'],
                'weight_kg' => $validated['weight_kg'],
                'nik_hash' => hash('sha256', preg_replace('/\s+/', '', $validated['nik'])),
                'nik_encrypted' => $validated['nik'],
                'bpjs_hash' => hash('sha256', preg_replace('/\s+/', '', $validated['bpjs'])),
                'bpjs_encrypted' => $validated['bpjs'],
                'alamat' => $validated['alamat'] ?? null,
                'geup' => $validated['geup'],
            ]);
        });

        return redirect()->route('athletes.index');
    }

    public function linkParent(Request $request, Athlete $athlete): RedirectResponse
    {
        abort_if($request->user()?->isParent(), 403);

        $validated = $request->validate([
            'parent_id' => ['required', 'exists:parents,parent_id'],
        ]);

        $athlete->update([
            'parent_id' => $validated['parent_id'],
        ]);

        return redirect()->route('athletes.index');
    }
}
