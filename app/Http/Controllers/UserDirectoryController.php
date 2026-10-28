<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FormatsPresentationData;
use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Group;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserDirectoryController extends Controller
{
    use FormatsPresentationData;

    public function index(): Response
    {
        $user = request()->user();
        abort_unless($user?->isAdmin(), 403);

        $athletes = Athlete::query()
            ->with(['user:id,name,email', 'branch:branch_id,branch_name', 'group:group_id,group_name'])
            ->latest('athlete_id')
            ->get();

        $athleteUsers = User::query()
            ->withRole('athlete')
            ->with([
                'profile',
                'athleteProfile.branch:branch_id,branch_name',
                'athleteProfile.group:group_id,group_name',
            ])
            ->get();

        $coachUsers = User::query()
            ->withRole('coach')
            ->with('coachProfile')
            ->get();

        return Inertia::render('AthletesPage', [
            'metrics' => [
                [
                    'label' => 'Active athlete records',
                    'value' => (string) $athletes->count(),
                    'detail' => $athletes->count().' active profiles in the roster',
                    'tone' => 'success',
                ],
                [
                    'label' => 'Athletes without guardian contact',
                    'value' => (string) $athletes->filter(fn (Athlete $a) => blank($a->guardian_name) && blank($a->guardian_phone))->count(),
                    'detail' => 'Optional guardian contact not set',
                    'tone' => 'info',
                ],
                [
                    'label' => 'Branches represented',
                    'value' => (string) $athletes->pluck('branch_id')->filter()->unique()->count(),
                    'detail' => 'Current roster spread across active branches',
                    'tone' => 'info',
                ],
            ],
            'rows' => $athleteUsers->map(function (User $directoryUser) {
                $athlete = $directoryUser->athleteProfile;
                $guardianSummary = collect([$athlete?->guardian_name, $athlete?->guardian_phone])
                    ->filter()
                    ->implode(' · ');

                return [
                    'id' => $directoryUser->uuid,
                    'user_id' => $directoryUser->uuid,
                    'athlete_id' => $athlete?->athlete_id,
                    'member_number' => $athlete?->member_number ?? 'Belum tersedia',
                    'joined_at' => $athlete?->joined_at?->format('d M Y') ?? '-',
                    'athlete' => $directoryUser->name ?? 'Unknown athlete',
                    'account_email' => $directoryUser->email ?? '-',
                    'guardian' => $guardianSummary !== '' ? $guardianSummary : 'Not set',
                    'guardian_name' => $athlete?->guardian_name ?? '',
                    'guardian_phone' => $athlete?->guardian_phone ?? '',
                    'guardian_relation' => $athlete?->guardian_relation ?? '',
                    'branch_id' => $athlete?->branch_id !== null ? (string) $athlete->branch_id : '',
                    'group_id' => $athlete?->group_id !== null ? (string) $athlete->group_id : '',
                    'branch' => $athlete?->branch?->branch_name ?? 'Unassigned',
                    'group' => $athlete?->group?->group_name ?? 'Unassigned',
                    'height_cm' => $athlete?->height_cm !== null
                        ? number_format((float) $athlete->height_cm, 1).' cm'
                        : '-',
                    'weight_kg' => $athlete?->weight_kg !== null
                        ? number_format((float) $athlete->weight_kg, 1).' kg'
                        : '-',
                    'nik' => $directoryUser->displayNik() ?: 'Not stored',
                    'bpjs' => $directoryUser->displayBpjs() ?: 'Not stored',
                    'geup' => str_replace('_', ' ', $athlete?->geup ?? 'GEUP_10'),
                    'status' => $athlete
                        ? $this->badge('Active', 'success')
                        : $this->badge('Profile incomplete', 'warning'),
                ];
            })->values(),
            'coachRows' => $coachUsers
                ->map(function (User $directoryUser) {
                    $coach = $directoryUser->coachProfile;

                    return [
                        'id' => $directoryUser->uuid,
                        'user_id' => $directoryUser->uuid,
                        'coach_id' => $coach?->coach_id,
                        'name' => $directoryUser->name ?? 'Unknown coach',
                        'email' => $directoryUser->email ?? '-',
                        'role' => 'Coach',
                        'status' => $this->badge('Active', 'success'),
                        'specialization' => $coach?->specialization ?? $coach?->license_type ?? '-',
                    ];
                })
                ->values(),
            'branches' => Branch::query()
                ->orderBy('branch_name')
                ->get(['branch_id as value', 'branch_name as label']),
            'groups' => Group::query()
                ->orderBy('group_name')
                ->get(['group_id as value', 'group_name as label']),
            'athletes' => $athletes
                ->map(fn (Athlete $athlete) => [
                    'value' => $athlete->athlete_id,
                    'label' => trim(($athlete->member_number ? $athlete->member_number.' · ' : '').($athlete->user?->name ?? 'Unknown athlete')),
                ])
                ->values(),
            'canViewSensitiveIdentifiers' => true,
        ]);
    }

    public function showByUser(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $user->loadMissing('profile');
        $athlete = Athlete::query()->where('id', $user->id)->first();

        if (! $athlete) {
            return response()->json([
                'user_id' => $user->id,
                'athlete_id' => null,
                'member_number' => null,
                'joined_at' => null,
                'name' => $user->name ?? '',
                'email' => $user->email ?? '',
                'gender' => $user->gender ?? 'MALE',
                'bday' => $user->bday?->format('Y-m-d') ?? '',
                'phone' => $user->phone ?? '',
                'height_cm' => '',
                'weight_kg' => '',
                'alamat' => $user->profile?->address ?? '',
                'branch_id' => '',
                'group_id' => '',
                'geup' => 'GEUP_10',
                'guardian_name' => '',
                'guardian_phone' => '',
                'guardian_relation' => '',
                'nik' => $user->displayNik(),
                'bpjs' => $user->displayBpjs(),
            ]);
        }

        $athlete->setRelation('user', $user);

        return response()->json($this->athleteFormPayload($athlete));
    }

    public function upsertByUser(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $validated = $this->validatedAthleteData($request, $user->id);

        DB::transaction(function () use ($validated, $user): void {
            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'gender' => $validated['gender'],
                'bday' => $validated['bday'],
                'phone' => $validated['phone'] ?? null,
            ]);

            Athlete::query()->updateOrCreate(
                ['id' => $user->id],
                $this->athleteRecordPayload($validated),
            );

            $profilePayload = [
                'address' => $validated['alamat'] ?? null,
            ];

            if (! empty($validated['nik'])) {
                $nik = preg_replace('/\s+/', '', $validated['nik']);
                $profilePayload['nik_hash'] = hash('sha256', $nik);
                $profilePayload['nik_ciphertext'] = $nik;
            }

            if (! empty($validated['bpjs'])) {
                $bpjs = preg_replace('/\s+/', '', $validated['bpjs']);
                $profilePayload['bpjs_hash'] = hash('sha256', $bpjs);
                $profilePayload['bpjs_ciphertext'] = $bpjs;
            }

            UserProfile::query()->updateOrCreate(['user_id' => $user->id], $profilePayload);
        });

        return redirect()->route('users.index');
    }

    private function validatedAthleteData(Request $request, int $userId): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId, 'id')],
            'gender' => ['required', Rule::in(['MALE', 'FEMALE'])],
            'bday' => ['required', 'date', 'before_or_equal:today'],
            'phone' => ['nullable', 'string', 'max:20'],
            'height_cm' => ['required', 'numeric', 'min:30', 'max:300'],
            'weight_kg' => ['required', 'numeric', 'min:1', 'max:500'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'geup' => ['required', Rule::in(['GEUP_1', 'GEUP_2', 'GEUP_3', 'GEUP_4', 'GEUP_5', 'GEUP_6', 'GEUP_7', 'GEUP_8', 'GEUP_9', 'GEUP_10', 'DAN'])],
            'branch_id' => ['required', 'exists:branches,branch_id'],
            'group_id' => ['required', 'exists:class_groups,group_id'],
            'guardian_name' => ['nullable', 'string', 'max:120'],
            'guardian_phone' => ['nullable', 'string', 'max:30'],
            'guardian_relation' => ['nullable', 'string', 'max:50'],
            'nik' => ['nullable', 'regex:/^\d{16}$/'],
            'bpjs' => ['nullable', 'regex:/^\d{13}$/'],
        ]);
    }

    private function athleteRecordPayload(array $validated): array
    {
        return [
            'group_id' => $validated['group_id'],
            'branch_id' => $validated['branch_id'],
            'height_cm' => $validated['height_cm'],
            'weight_kg' => $validated['weight_kg'],
            'geup' => $validated['geup'],
            'guardian_name' => $validated['guardian_name'] ?? null,
            'guardian_phone' => $validated['guardian_phone'] ?? null,
            'guardian_relation' => $validated['guardian_relation'] ?? null,
        ];
    }

    private function athleteFormPayload(Athlete $athlete): array
    {
        return [
            'user_id' => $athlete->user?->uuid,
            'athlete_id' => $athlete->athlete_id,
            'member_number' => $athlete->member_number,
            'joined_at' => $athlete->joined_at?->format('Y-m-d'),
            'name' => $athlete->user?->name ?? '',
            'email' => $athlete->user?->email ?? '',
            'gender' => $athlete->user?->gender ?? 'MALE',
            'bday' => $athlete->user?->bday?->format('Y-m-d') ?? '',
            'phone' => $athlete->user?->phone ?? '',
            'height_cm' => (string) ($athlete->height_cm ?? ''),
            'weight_kg' => (string) ($athlete->weight_kg ?? ''),
            'alamat' => $athlete->user?->profile?->address ?? $athlete->alamat ?? '',
            'branch_id' => (string) ($athlete->branch_id ?? ''),
            'group_id' => (string) ($athlete->group_id ?? ''),
            'geup' => $athlete->geup ?? 'GEUP_10',
            'guardian_name' => (string) ($athlete->guardian_name ?? ''),
            'guardian_phone' => (string) ($athlete->guardian_phone ?? ''),
            'guardian_relation' => (string) ($athlete->guardian_relation ?? ''),
            'nik' => $athlete->user?->displayNik() ?? '',
            'bpjs' => $athlete->user?->displayBpjs() ?? '',
        ];
    }
}
