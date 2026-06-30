<?php

namespace App\Http\Controllers;

use App\Actions\Profiles\SaveUserAchievement;
use App\Actions\Profiles\SaveUserCertification;
use App\Actions\Profiles\UpdateAccountProfile;
use App\Actions\Profiles\UpdateAthleteProfile;
use App\Actions\Profiles\UpdateCoachProfile;
use App\Actions\Profiles\UpdateParentProfile;
use App\Http\Requests\Profiles\SaveUserAchievementRequest;
use App\Http\Requests\Profiles\SaveUserCertificationRequest;
use App\Http\Requests\Profiles\UpdateAccountProfileRequest;
use App\Http\Requests\Profiles\UpdateAthleteProfileRequest;
use App\Http\Requests\Profiles\UpdateCoachProfileRequest;
use App\Http\Requests\Profiles\UpdateParentProfileRequest;
use App\Models\Branch;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Group;
use App\Models\InvoiceTemplate;
use App\Models\ParentProfile;
use App\Models\Payment;
use App\Models\Session;
use App\Models\Attendance;
use App\Models\Athlete;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserCertification;
use App\Models\UserRoleAssignment;
use App\Support\ActivityLogger;
use App\Support\Profile\ProfilePageData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    public function index(): Response
    {
        abort_unless(request()->user()?->isAdmin(), 403);

        $hasAccountStatus = Schema::hasColumn('users', 'account_status');

        $users = User::query()
            ->withTrashed()
            ->with([
                'roleAssignments',
                'athleteProfile.branch:branch_id,branch_name',
                'coachProfile',
                'parentProfile.athletes.branch:branch_id,branch_name',
                'profile',
            ])
            ->latest('id')
            ->get();

        return Inertia::render('AdminPage', [
            'users' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name ?? 'Unnamed user',
                'email' => $user->email,
                'role' => $user->role,
                'roles' => $user->assignedRoles(),
                'branch' => $this->branchLabel($user),
                'status' => $hasAccountStatus ? ($user->account_status ?? 'active') : 'active',
                'createdAt' => $user->created_at?->format('d M Y') ?? '-',
                'deletedAt' => $user->deleted_at?->format('d M Y H:i:s'),
            ])->values(),
            'branches' => Branch::query()
                ->orderBy('branch_name')
                ->get()
                ->map(fn (Branch $branch) => [
                    'id' => (string) $branch->branch_id,
                    'name' => $branch->branch_name,
                    'location' => $branch->location,
                ])
                ->values(),
            'groups' => Group::query()
                ->orderBy('group_name')
                ->get()
                ->map(fn (Group $group) => [
                    'id' => (string) $group->group_id,
                    'name' => $group->group_name,
                    'description' => $group->description,
                ])
                ->values(),
            'debugbar' => [
                'enabled' => class_exists(\Barryvdh\Debugbar\ServiceProvider::class),
            ],
            'importResult' => session('importResult'),
            'invoiceTemplate' => Schema::hasTable('invoice_templates')
                ? InvoiceTemplate::query()->firstOrCreate(
                    ['name' => 'default'],
                    ['company_name' => 'RF IS'],
                )
                : null,
        ]);
    }

    public function updateInvoiceTemplate(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if (! Schema::hasTable('invoice_templates')) {
            return redirect()->route('admin.index')
                ->withErrors(['invoice_template' => 'invoice_templates table does not exist yet. Run migrations first.']);
        }

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:150'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:60'],
            'company_email' => ['nullable', 'email', 'max:120'],
            'logo_url' => ['nullable', 'url', 'max:255'],
            'header_text' => ['nullable', 'string', 'max:255'],
            'footer_text' => ['nullable', 'string'],
            'payment_notes' => ['nullable', 'string'],
        ]);

        InvoiceTemplate::query()->updateOrCreate(
            ['name' => 'default'],
            $validated,
        );

        ActivityLogger::log($request, 'admin.invoice-template.updated', 'admin', 'Updated invoice template settings');

        return back();
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $this->validateAccount($request);
        $roles = $validated['roles'];
        $primaryRole = $this->resolvePrimaryRole($roles);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'gender' => 'MALE',
            'role' => $primaryRole,
            ...(Schema::hasColumn('users', 'account_status') ? ['account_status' => $validated['status']] : []),
        ]);

        $this->syncUserRoles($user, $roles);
        $this->syncRoleProfile($user, $roles);
        ActivityLogger::log($request, 'admin.account.created', 'admin', 'Created user account', $user, ['role' => $user->role]);

        return back();
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $this->validateAccount($request, $user);
        $roles = $validated['roles'];
        $primaryRole = $this->resolvePrimaryRole($roles);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $primaryRole,
            ...(! empty($validated['password']) ? ['password' => Hash::make($validated['password'])] : []),
            ...(Schema::hasColumn('users', 'account_status') ? ['account_status' => $validated['status']] : []),
        ]);

        $this->syncUserRoles($user, $roles);
        $this->syncRoleProfile($user, $roles);
        ActivityLogger::log($request, 'admin.account.updated', 'admin', 'Updated user account', $user, ['role' => $user->role]);

        return redirect()->route('admin.index');
    }

    public function updateAccountProfile(UpdateAccountProfileRequest $request, User $user, UpdateAccountProfile $updateAccountProfile): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $updateAccountProfile->handle($user, $request->validated(), $request);
        ActivityLogger::log($request, 'admin.account.profile.updated', 'admin', 'Updated account roster profile', $user, ['user_id' => $user->id]);

        return back();
    }

    public function updateAthleteProfile(UpdateAthleteProfileRequest $request, User $user, UpdateAthleteProfile $updateAthleteProfile): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($user->hasRole('athlete'), 404);

        $updateAthleteProfile->handle($user, $request->validated());

        return back();
    }

    public function updateCoachProfile(UpdateCoachProfileRequest $request, User $user, UpdateCoachProfile $updateCoachProfile): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($user->hasRole('coach'), 404);

        $updateCoachProfile->handle($user, $request->validated());

        return back();
    }

    public function updateParentProfile(UpdateParentProfileRequest $request, User $user, UpdateParentProfile $updateParentProfile): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($user->hasRole('parent'), 404);

        $updateParentProfile->handle($user, $request->validated());

        return back();
    }

    public function storeUserCertification(SaveUserCertificationRequest $request, User $user, SaveUserCertification $saveUserCertification): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $saveUserCertification->store($user, $request->validated(), $request);

        return back();
    }

    public function updateUserCertification(SaveUserCertificationRequest $request, User $user, UserCertification $certification, SaveUserCertification $saveUserCertification): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless((int) $certification->user_id === (int) $user->id, 404);

        $saveUserCertification->update($user, $certification, $request->validated(), $request);

        return back();
    }

    public function storeUserAchievement(SaveUserAchievementRequest $request, User $user, SaveUserAchievement $saveUserAchievement): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $saveUserAchievement->store($user, $request->validated(), $request);

        return back();
    }

    public function updateUserAchievement(SaveUserAchievementRequest $request, User $user, UserAchievement $achievement, SaveUserAchievement $saveUserAchievement): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless((int) $achievement->user_id === (int) $user->id, 404);

        $saveUserAchievement->update($user, $achievement, $request->validated(), $request);

        return back();
    }

    public function destroyAccount(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if ((int) $request->user()?->id === (int) $user->id) {
            return back()->withErrors(['account' => 'You cannot delete your own account.']);
        }

        $user->delete();
        ActivityLogger::log($request, 'admin.account.deleted', 'admin', 'Soft deleted user account', $user, ['user_id' => $user->id]);

        return redirect()->route('admin.index');
    }

    public function restoreAccount(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $user = User::withTrashed()->findOrFail($id);

        $user->restore();
        ActivityLogger::log($request, 'admin.account.restored', 'admin', 'Restored soft deleted user account', $user, ['user_id' => $user->id]);

        return redirect()->route('admin.index');
    }

    public function hardDelete(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $user = User::withTrashed()->findOrFail($id);

        if ((int) $request->user()?->id === (int) $user->id) {
            return back()->withErrors(['account' => 'You cannot delete your own account.']);
        }
        if (! $user->trashed()) {
            return back()->withErrors(['account' => 'You can only hard delete an account that has been soft deleted.']);
        }
        $user->forceDelete();
        ActivityLogger::log($request, 'admin.account.force_deleted', 'admin', 'Permanently deleted user account', null, ['user_id' => $id]);

        return redirect()->route('admin.index');
    }

    public function show(User $user, ProfilePageData $profilePageData): Response
    {
        abort_unless(request()->user()?->isAdmin(), 403);

        $profilePageData->loadUser($user);

        return Inertia::render('profiles/ProfileDetailsPage', [
            'user' => $profilePageData->user($user),
            'context' => 'admin',
            'canEditAccount' => false,
            'canEditRoleProfiles' => true,
            'branches' => $profilePageData->branchOptions(),
            'groups' => $profilePageData->groupOptions(),
        ]);
    }

    public function storeBranch(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'location' => ['required', 'string', 'max:255'],
        ]);

        $branch = Branch::create([
            'branch_name' => $validated['name'],
            'location' => $validated['location'],
        ]);
        ActivityLogger::log($request, 'admin.branch.created', 'admin', 'Created branch', $branch, ['branch_name' => $branch->branch_name]);

        return redirect()->route('admin.index');
    }

    public function updateBranch(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'location' => ['required', 'string', 'max:255'],
        ]);

        $branch->update([
            'branch_name' => $validated['name'],
            'location' => $validated['location'],
        ]);
        ActivityLogger::log($request, 'admin.branch.updated', 'admin', 'Updated branch', $branch, ['branch_name' => $branch->branch_name]);

        return redirect()->route('admin.index');
    }

    public function destroyBranch(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        ActivityLogger::log($request, 'admin.branch.deleted', 'admin', 'Deleted branch', $branch, ['branch_name' => $branch->branch_name]);
        $branch->delete();

        return redirect()->route('admin.index');
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $group = Group::create([
            'group_name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);
        ActivityLogger::log($request, 'admin.group.created', 'admin', 'Created group', $group, ['group_name' => $group->group_name]);

        return redirect()->route('admin.index');
    }

    public function updateGroup(Request $request, Group $group): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $group->update([
            'group_name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);
        ActivityLogger::log($request, 'admin.group.updated', 'admin', 'Updated group', $group, ['group_name' => $group->group_name]);

        return redirect()->route('admin.index');
    }

    public function destroyGroup(Request $request, Group $group): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        ActivityLogger::log($request, 'admin.group.deleted', 'admin', 'Deleted group', $group, ['group_name' => $group->group_name]);
        $group->delete();

        return redirect()->route('admin.index');
    }

    public function importCsv(Request $request)
    {
        $request->validate([
            'entity' => 'required|string',
            'file' => 'required|file|mimes:csv,txt|max:10240', // Max 10MB
        ]);

        $file = $request->file('file');
        $csvData = array_map('str_getcsv', file($file->getRealPath()));
        
        if (count($csvData) < 2) {
            return back()->withErrors(['file' => 'The CSV file is empty or missing data rows.']);
        }

        $headers = array_map('strtolower', array_map('trim', $csvData[0]));
        $rows = array_slice($csvData, 1);

        $imported = 0;
        $failed = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            if (count(array_filter($row)) === 0) continue; 
            
            $data = array_combine($headers, array_pad($row, count($headers), null));

            try {
                DB::beginTransaction();

                $email = trim($data['email'] ?? '');
                if (empty($email)) {
                    $cleanName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $data['name']));
                    $email = $cleanName . rand(100,999) . '@rfis.com';
                }

                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => trim($data['name']),
                        'phone' => trim($data['phone'] ?? '') ?: null,
                        'password' => Hash::make('password123'), // Default password
                    ]
                );

                $role = strtolower(trim($data['role'] ?? 'athlete'));

                $bday = null;
                if (!empty($data['bday'])) {
                    try {
                        $bday = Carbon::parse(trim($data['bday']))->format('Y-m-d');
                    } catch (\Exception $e) {
                        $bday = null;
                    }
                }

                if ($role === 'athlete') {
                    Athlete::updateOrCreate(
                        ['id' => $user->id],
                        [
                            'gender' => strtoupper(trim($data['gender'] ?? '')) === 'M' ? 'MALE' : 'FEMALE',
                            'date_of_birth' => $bday,
                            'height_cm' => floatval($data['height_cm'] ?? 0), 
                            'weight_kg' => floatval($data['weight_kg'] ?? 0),
                            'alamat' => trim($data['alamat'] ?? '') ?: null,
                            'branch_id' => intval($data['branch_id'] ?? 0) ?: null,
                            'group_id' => intval($data['group_id'] ?? 0) ?: null,
                            'geup' => !empty($data['geup']) ? 'GEUP_' . trim($data['geup']) : 'GEUP_10',
                            'nik_hash' => trim($data['nik'] ?? '') ? hash('sha256', trim($data['nik'])) : null,
                            'nik_ciphertext' => trim($data['nik'] ?? '') ?: null,
                            'bpjs_hash' => trim($data['bpjs'] ?? '') ? hash('sha256', trim($data['bpjs'])) : null,
                            'bpjs_ciphertext' => trim($data['bpjs'] ?? '') ?: null,
                        ]
                    );
                } elseif ($role === 'coach') {
                    \App\Models\Coach::updateOrCreate(
                        ['id' => $user->id],
                        [
                            'status' => 'active',
                        ] // If your migration requires specific default fields for coaches, add them here
                    );
                } elseif ($role === 'admin') {
                    // If you have a specific Admin table or Spatie Role assignment, put it here.
                }

                DB::commit();
                $imported++;

            } catch (\Exception $e) {
                DB::rollBack();
                $failed++;
                $errors[] = "Row " . ($index + 2) . " (" . ($data['name'] ?? 'Unknown') . "): " . $e->getMessage();
            }
        }

        return back()->with([
            'importResult' => [
                'entity' => $request->entity,
                'imported' => $imported,
                'failed' => $failed,
                'errors' => $errors,
            ]
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $entity = $request->validate([
            'entity' => ['required', Rule::in(['athletes', 'payments', 'sessions', 'attendance', 'events', 'event_registrations'])],
        ])['entity'];

        [$headers, $rows] = $this->exportRows($entity);
        $filename = $entity.'_export_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function downloadTemplate(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $entity = $request->validate([
            'entity' => ['required', Rule::in(['athletes', 'payments', 'sessions', 'attendance', 'events', 'event_registrations'])],
        ])['entity'];

        [$headers] = $this->templateRows($entity);
        $filename = $entity.'_template.csv';

        return response()->streamDownload(function () use ($headers): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function validateAccount(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', Rule::in(['admin', 'coach', 'parent', 'athlete'])],
            'status' => ['required', Rule::in(['active', 'invited', 'suspended'])],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
        ]);
    }

    private function branchLabel(User $user): string
    {
        if ($user->hasRole('athlete')) {
            return $user->athleteProfile?->branch?->branch_name ?? 'Unassigned';
        }

        if ($user->hasRole('parent')) {
            return $user->parentProfile?->athletes
                ?->pluck('branch.branch_name')
                ->filter()
                ->unique()
                ->implode(', ') ?: 'Linked by child';
        }

        if ($user->hasRole('coach')) {
            return $user->coachProfile ? 'Coaching team' : 'Unassigned';
        }

        return 'Head Office';
    }

    private function syncRoleProfile(User $user, array $roles): void
    {
        $hasParentRole = in_array('parent', $roles, true);
        $hasCoachRole = in_array('coach', $roles, true);
        $hasAthleteRole = in_array('athlete', $roles, true);

        if ($hasParentRole && ($hasCoachRole || $hasAthleteRole)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'roles' => 'Parent cannot be combined with athlete or coach.',
            ]);
        }

        if ($hasParentRole) {
            // Parent accounts are exclusive and cannot carry athlete/coach profiles.
            if ($user->athleteProfile()->exists()) {
                $user->athleteProfile()->delete();
            }
            if ($user->coachProfile()->exists()) {
                $user->coachProfile()->delete();
            }
        }

        if ($hasParentRole) {
            ParentProfile::firstOrCreate(
                ['id' => $user->id],
                ['relation' => 'guardian'],
            );
        } else {
            $user->parentProfile()->delete();
        }

        if ($hasCoachRole) {
            Coach::firstOrCreate(
                ['id' => $user->id],
                ['status' => 'active'],
            );
        } else {
            $user->coachProfile()->delete();
        }
    }

    private function syncUserRoles(User $user, array $roles): void
    {
        $roles = collect($roles)->unique()->values();
        $existing = UserRoleAssignment::query()->where('user_id', $user->id)->pluck('role')->all();

        $toDelete = array_values(array_diff($existing, $roles->all()));
        if (count($toDelete) > 0) {
            UserRoleAssignment::query()
                ->where('user_id', $user->id)
                ->whereIn('role', $toDelete)
                ->delete();
        }

        foreach ($roles as $role) {
            UserRoleAssignment::query()->firstOrCreate([
                'user_id' => $user->id,
                'role' => $role,
            ]);
        }
    }

    private function resolvePrimaryRole(array $roles): string
    {
        $priority = ['admin', 'coach', 'parent', 'athlete'];
        foreach ($priority as $role) {
            if (in_array($role, $roles, true)) {
                return $role;
            }
        }

        return 'athlete';
    }

    private function readCsvRows(string $path): array
    {
        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle);
        $headers = array_map(fn ($value) => strtolower(trim((string) $value)), $headers ?: []);
        $rows = [];

        while (($line = fgetcsv($handle)) !== false) {
            if ($line === [null] || count(array_filter($line, fn ($cell) => trim((string) $cell) !== '')) === 0) {
                continue;
            }
            $rows[] = array_combine($headers, array_pad($line, count($headers), null));
        }
        fclose($handle);

        return [$headers, $rows];
    }

    private function importRow(string $entity, array $row): void
    {
        match ($entity) {
            'athletes' => $this->importAthleteRow($row),
            'payments' => $this->importPaymentRow($row),
            'sessions' => $this->importSessionRow($row),
            'attendance' => $this->importAttendanceRow($row),
            'events' => $this->importEventRow($row),
            'event_registrations' => $this->importEventRegistrationRow($row),
            default => throw new \RuntimeException('Unsupported entity.'),
        };
    }

    private function importAthleteRow(array $row): void
    {
        $email = trim((string) ($row['email'] ?? ''));
        $name = trim((string) ($row['name'] ?? ''));
        if ($email === '' || $name === '') {
            throw new \RuntimeException('name and email are required.');
        }

        DB::transaction(function () use ($row, $email, $name): void {
            $user = User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('InitialPass123!'),
                    'gender' => strtoupper((string) ($row['gender'] ?? 'MALE')),
                    'role' => 'athlete',
                    'bday' => $this->nullableDate($row['bday'] ?? null),
                    'phone' => $this->nullableString($row['phone'] ?? null),
                ],
            );

            $user->update([
                'name' => $name,
                'gender' => strtoupper((string) ($row['gender'] ?? 'MALE')),
                'bday' => $this->nullableDate($row['bday'] ?? null),
                'phone' => $this->nullableString($row['phone'] ?? null),
            ]);

            Athlete::query()->updateOrCreate(
                ['id' => $user->id],
                [
                    'branch_id' => (int) ($row['branch_id'] ?? 0),
                    'group_id' => $this->nullableInt($row['group_id'] ?? null),
                    'parent_id' => $this->nullableInt($row['parent_id'] ?? null),
                    'height_cm' => (float) ($row['height_cm'] ?? 0),
                    'weight_kg' => (float) ($row['weight_kg'] ?? 0),
                    'alamat' => $this->nullableString($row['alamat'] ?? null),
                    'geup' => strtoupper((string) ($row['geup'] ?? 'GEUP_10')),
                    'nik_hash' => ! empty($row['nik']) ? hash('sha256', preg_replace('/\s+/', '', (string) $row['nik'])) : null,
                    'nik_ciphertext' => $this->nullableString($row['nik'] ?? null),
                    'bpjs_hash' => ! empty($row['bpjs']) ? hash('sha256', preg_replace('/\s+/', '', (string) $row['bpjs'])) : null,
                    'bpjs_ciphertext' => $this->nullableString($row['bpjs'] ?? null),
                ],
            );
        });
    }

    private function importPaymentRow(array $row): void
    {
        Payment::query()->create([
            'athlete_id' => (string) ($row['athlete_id'] ?? ''),
            'payment_type' => strtoupper((string) ($row['payment_type'] ?? 'OTHER')),
            'amount' => (float) ($row['total_amount'] ?? 0),
            'reference_id' => $this->nullableString($row['reference_id'] ?? null),
            'total_amount' => (float) ($row['total_amount'] ?? 0),
            'paid_amount' => (float) ($row['paid_amount'] ?? 0),
            'remaining_amount' => max((float) ($row['total_amount'] ?? 0) - (float) ($row['paid_amount'] ?? 0), 0),
            'payment_date' => $this->nullableDate($row['payment_date'] ?? null) ?? now()->toDateString(),
            'status' => strtoupper((string) ($row['status'] ?? 'PENDING')),
            'notes' => $this->nullableString($row['notes'] ?? null),
        ]);
    }

    private function importSessionRow(array $row): void
    {
        Session::query()->create([
            'coach_id' => $this->nullableString($row['coach_id'] ?? null),
            'branch_id' => (int) ($row['branch_id'] ?? 0),
            'group_id' => $this->nullableInt($row['group_id'] ?? null),
            'title' => (string) ($row['title'] ?? ''),
            'location' => $this->nullableString($row['location'] ?? null),
            'session_date' => $this->nullableDate($row['session_date'] ?? null) ?? now()->toDateString(),
            'start_time' => (string) ($row['start_time'] ?? '08:00'),
            'end_time' => (string) ($row['end_time'] ?? '09:00'),
            'status' => strtoupper((string) ($row['status'] ?? 'DRAFT')),
        ]);
    }

    private function importAttendanceRow(array $row): void
    {
        Attendance::query()->create([
            'athlete_id' => (string) ($row['athlete_id'] ?? ''),
            'coach_session_id' => (int) ($row['coach_session_id'] ?? 0),
            'date' => $this->nullableDate($row['date'] ?? null) ?? now()->toDateString(),
            'status' => strtoupper((string) ($row['status'] ?? 'ABSENT')),
            'checked_in_at' => ! empty($row['checked_in_time']) ? Carbon::parse((string) $row['date'].' '.(string) $row['checked_in_time']) : null,
            'notes' => $this->nullableString($row['notes'] ?? null),
            'follow_up_owner' => $this->nullableString($row['follow_up_owner'] ?? null),
        ]);
    }

    private function importEventRow(array $row): void
    {
        Event::query()->create([
            'e_name' => (string) ($row['e_name'] ?? ''),
            'e_date' => $this->nullableDate($row['e_date'] ?? null) ?? now()->toDateString(),
            'location' => $this->nullableString($row['location'] ?? null),
            'level' => $this->nullableString($row['level'] ?? null),
            'entry_fee' => (float) ($row['entry_fee'] ?? 0),
            'max_slots' => (int) ($row['max_slots'] ?? 0),
            'description' => $this->nullableString($row['description'] ?? null),
            'organizer' => $this->nullableString($row['organizer'] ?? null),
            'contact_info' => $this->nullableString($row['contact_info'] ?? null),
            'sponsors' => $this->nullableString($row['sponsors'] ?? null),
            'status' => strtoupper((string) ($row['status'] ?? 'SCHEDULED')),
            'poster_url' => $this->nullableString($row['poster_url'] ?? null),
        ]);
    }

    private function importEventRegistrationRow(array $row): void
    {
        EventRegistration::query()->create([
            'athlete_id' => (string) ($row['athlete_id'] ?? ''),
            'event_id' => (int) ($row['event_id'] ?? 0),
            'category' => strtoupper((string) ($row['category'] ?? 'UNKNOWN')),
            'division' => $this->nullableString($row['division'] ?? null),
            'status' => strtoupper((string) ($row['status'] ?? 'PENDING')),
        ]);
    }

    private function exportRows(string $entity): array
    {
        return match ($entity) {
            'athletes' => $this->templateRows('athletes', Athlete::query()->with('user')->get()->map(fn ($item) => [
                $item->user?->name, $item->user?->email, $item->user?->gender, optional($item->user?->bday)->format('Y-m-d'),
                $item->user?->phone, $item->height_cm, $item->weight_kg, $item->alamat, $item->branch_id, $item->group_id,
                $item->geup, $item->parent_id, $item->nikdisplayValue(), $item->bpjsdisplayValue(),
            ])->all()),
            'payments' => $this->templateRows('payments', Payment::query()->get()->map(fn ($item) => [
                $item->athlete_id, $item->payment_type, $item->total_amount, $item->paid_amount, optional($item->payment_date)->format('Y-m-d'),
                $item->status, $item->reference_id, $item->notes,
            ])->all()),
            'sessions' => $this->templateRows('sessions', Session::query()->get()->map(fn ($item) => [
                $item->title, $item->branch_id, $item->group_id, $item->coach_id, $item->location,
                optional($item->session_date)->format('Y-m-d'), $item->start_time, $item->end_time, $item->status,
            ])->all()),
            'attendance' => $this->templateRows('attendance', Attendance::query()->get()->map(fn ($item) => [
                $item->athlete_id, $item->coach_session_id, optional($item->date)->format('Y-m-d'),
                $item->status, optional($item->checked_in_at)->format('H:i'), $item->notes, $item->follow_up_owner,
            ])->all()),
            'events' => $this->templateRows('events', Event::query()->get()->map(fn ($item) => [
                $item->e_name, optional($item->e_date)->format('Y-m-d'), $item->location, $item->level, $item->entry_fee,
                $item->max_slots, $item->description, $item->organizer, $item->contact_info, $item->sponsors, $item->status, $item->poster_url,
            ])->all()),
            'event_registrations' => $this->templateRows('event_registrations', EventRegistration::query()->get()->map(fn ($item) => [
                $item->athlete_id, $item->event_id, $item->category, $item->division, $item->status,
            ])->all()),
            default => [[], []],
        };
    }

    private function templateRows(string $entity, array $rows = []): array
    {
        $headers = match ($entity) {
            'athletes' => ['name', 'email', 'gender', 'bday', 'phone', 'height_cm', 'weight_kg', 'alamat', 'branch_id', 'group_id', 'geup', 'parent_id', 'nik', 'bpjs'],
            'payments' => ['athlete_id', 'payment_type', 'total_amount', 'paid_amount', 'payment_date', 'status', 'reference_id', 'notes'],
            'sessions' => ['title', 'branch_id', 'group_id', 'coach_id', 'location', 'session_date', 'start_time', 'end_time', 'status'],
            'attendance' => ['athlete_id', 'coach_session_id', 'date', 'status', 'checked_in_time', 'notes', 'follow_up_owner'],
            'events' => ['e_name', 'e_date', 'location', 'level', 'entry_fee', 'max_slots', 'description', 'organizer', 'contact_info', 'sponsors', 'status', 'poster_url'],
            'event_registrations' => ['athlete_id', 'event_id', 'category', 'division', 'status'],
            default => [],
        };

        return [$headers, $rows];
    }

    private function nullableString(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));
        return $trimmed === '' ? null : $trimmed;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int) $value;
    }

    private function nullableDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return Carbon::parse((string) $value)->format('Y-m-d');
    }
}
