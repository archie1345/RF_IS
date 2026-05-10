<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FormatsMvpData;
use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Group;
use App\Models\Parents;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $athleteUsers = User::query()
            ->with(['roleAssignments', 'athleteProfile.branch:branch_id,branch_name', 'athleteProfile.group:group_id,group_name', 'athleteProfile.parent.user:id,name'])
            ->whereNull('deleted_at')
            ->get()
            ->filter(fn (User $user) => $user->hasRole('athlete'))
            ->values();

        return Inertia::render('AthletesPage', [
            'metrics' => [
                ['label' => 'Active athlete records', 'value' => (string) $athletes->count(), 'detail' => $athletes->whereNull('deleted_at')->count().' active profiles in the roster', 'tone' => 'success'],
                ['label' => 'Profiles missing parent links', 'value' => (string) $athletes->whereNull('parent_id')->count(), 'detail' => 'Records still need parent connection', 'tone' => 'warning'],
                ['label' => 'Branches represented', 'value' => (string) $athletes->pluck('branch_id')->filter()->unique()->count(), 'detail' => 'Current roster spread across active branches', 'tone' => 'info'],
            ],
            'rows' => $athleteUsers->map(function (User $user) use ($canViewSensitiveIdentifiers) {
                $athlete = $user->athleteProfile;
                return [
                    'id' => 'USR-'.$user->id,
                    'user_id' => $user->id,
                    'athlete_id' => $athlete?->athlete_id,
                    'athlete' => $user->name ?? 'Unknown athlete',
                    'account_email' => $user->email ?? '-',
                    'parent' => $athlete?->parent?->user?->name ?? 'Not linked',
                    'branch' => $athlete?->branch?->branch_name ?? 'Unassigned',
                    'group' => $athlete?->group?->group_name ?? 'Unassigned',
                    'height_cm' => $athlete?->height_cm !== null ? number_format((float) $athlete->height_cm, 1).' cm' : '-',
                    'weight_kg' => $athlete?->weight_kg !== null ? number_format((float) $athlete->weight_kg, 1).' kg' : '-',
                    'nik' => $canViewSensitiveIdentifiers ? ($athlete?->nik_encrypted ?? 'Not stored') : null,
                    'bpjs' => $canViewSensitiveIdentifiers ? ($athlete?->bpjs_encrypted ?? 'Not stored') : null,
                    'geup' => str_replace('_', ' ', $athlete?->geup ?? 'GEUP_10'),
                    'status' => $athlete
                        ? $this->badge($athlete->parent_id ? 'Active' : 'Awaiting parent link', $athlete->parent_id ? 'success' : 'warning')
                        : $this->badge('Profile incomplete', 'warning'),
                ];
            })->values(),
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

    public function show(Request $request, Athlete $athlete): JsonResponse
    {
        abort_if($request->user()?->isParent() && ! $request->user()?->children()->where('athlete_id', $athlete->athlete_id)->exists(), 403);

        $athlete->loadMissing(['user:id,name,email,gender,bday,phone', 'branch:branch_id,branch_name', 'group:group_id,group_name', 'parent:parent_id,id']);

        return response()->json([
            'athlete_id' => $athlete->athlete_id,
            'name' => $athlete->user?->name ?? '',
            'email' => $athlete->user?->email ?? '',
            'gender' => $athlete->user?->gender ?? 'MALE',
            'bday' => $athlete->user?->bday?->format('Y-m-d') ?? '',
            'phone' => $athlete->user?->phone ?? '',
            'height_cm' => (string) ($athlete->height_cm ?? ''),
            'weight_kg' => (string) ($athlete->weight_kg ?? ''),
            'alamat' => $athlete->alamat ?? '',
            'branch_id' => (string) ($athlete->branch_id ?? ''),
            'group_id' => (string) ($athlete->group_id ?? ''),
            'geup' => $athlete->geup ?? 'GEUP_10',
            'parent_id' => (string) ($athlete->parent_id ?? ''),
            'nik' => (string) ($athlete->nik_encrypted ?? ''),
            'bpjs' => (string) ($athlete->bpjs_encrypted ?? ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort(403, 'Create athlete account from Admin Panel only.');
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

    public function update(Request $request, Athlete $athlete): RedirectResponse
    {
        abort_if($request->user()?->isParent(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($athlete->id, 'id')],
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
            'nik' => ['nullable', 'string', 'max:50'],
            'bpjs' => ['nullable', 'string', 'max:50'],
        ]);

        DB::transaction(function () use ($validated, $athlete): void {
            $athlete->user()->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'gender' => $validated['gender'],
                'bday' => $validated['bday'],
                'phone' => $validated['phone'] ?? null,
            ]);

            $athletePayload = [
                'group_id' => $validated['group_id'],
                'branch_id' => $validated['branch_id'],
                'parent_id' => $validated['parent_id'] ?? null,
                'height_cm' => $validated['height_cm'],
                'weight_kg' => $validated['weight_kg'],
                'alamat' => $validated['alamat'] ?? null,
                'geup' => $validated['geup'],
            ];

            if (! empty($validated['nik'])) {
                $athletePayload['nik_hash'] = hash('sha256', preg_replace('/\s+/', '', $validated['nik']));
                $athletePayload['nik_encrypted'] = $validated['nik'];
            }

            if (! empty($validated['bpjs'])) {
                $athletePayload['bpjs_hash'] = hash('sha256', preg_replace('/\s+/', '', $validated['bpjs']));
                $athletePayload['bpjs_encrypted'] = $validated['bpjs'];
            }

            $athlete->update($athletePayload);
        });

        return redirect()->route('athletes.index');
    }

    public function destroy(Request $request, Athlete $athlete): RedirectResponse
    {
        abort(403, 'Delete athlete account from Admin Panel only.');
    }

    public function showByUser(Request $request, User $user): JsonResponse
    {
        $athlete = Athlete::query()->where('id', $user->id)->first();

        if ($request->user()?->isParent() && $athlete && ! $request->user()?->children()->where('athlete_id', $athlete->athlete_id)->exists()) {
            abort(403);
        }

        return response()->json([
            'user_id' => $user->id,
            'athlete_id' => $athlete?->athlete_id,
            'name' => $user->name ?? '',
            'email' => $user->email ?? '',
            'gender' => $user->gender ?? 'MALE',
            'bday' => $user->bday?->format('Y-m-d') ?? '',
            'phone' => $user->phone ?? '',
            'height_cm' => (string) ($athlete?->height_cm ?? ''),
            'weight_kg' => (string) ($athlete?->weight_kg ?? ''),
            'alamat' => $athlete?->alamat ?? '',
            'branch_id' => (string) ($athlete?->branch_id ?? ''),
            'group_id' => (string) ($athlete?->group_id ?? ''),
            'geup' => $athlete?->geup ?? 'GEUP_10',
            'parent_id' => (string) ($athlete?->parent_id ?? ''),
            'nik' => (string) ($athlete?->nik_encrypted ?? ''),
            'bpjs' => (string) ($athlete?->bpjs_encrypted ?? ''),
        ]);
    }

    public function upsertByUser(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()?->isParent(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id, 'id')],
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
            'nik' => ['nullable', 'string', 'max:50'],
            'bpjs' => ['nullable', 'string', 'max:50'],
        ]);

        DB::transaction(function () use ($validated, $user): void {
            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'gender' => $validated['gender'],
                'bday' => $validated['bday'],
                'phone' => $validated['phone'] ?? null,
            ]);

            $payload = [
                'group_id' => $validated['group_id'],
                'branch_id' => $validated['branch_id'],
                'parent_id' => $validated['parent_id'] ?? null,
                'height_cm' => $validated['height_cm'],
                'weight_kg' => $validated['weight_kg'],
                'alamat' => $validated['alamat'] ?? null,
                'geup' => $validated['geup'],
                'nik_hash' => ! empty($validated['nik']) ? hash('sha256', preg_replace('/\s+/', '', $validated['nik'])) : str_repeat('0', 64),
                'nik_encrypted' => $validated['nik'] ?? null,
                'bpjs_hash' => ! empty($validated['bpjs']) ? hash('sha256', preg_replace('/\s+/', '', $validated['bpjs'])) : str_repeat('0', 64),
                'bpjs_encrypted' => $validated['bpjs'] ?? null,
            ];

            Athlete::query()->updateOrCreate(
                ['id' => $user->id],
                $payload,
            );
        });

        return redirect()->route('athletes.index');
    }
}

