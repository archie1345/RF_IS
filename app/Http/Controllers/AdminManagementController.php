<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Coach;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Group;
use App\Models\InvoiceTemplate;
use App\Models\Parents;
use App\Models\Payment;
use App\Models\Session;
use App\Models\Attendance;
use App\Models\Athlete;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserCertification;
use App\Models\UserFile;
use App\Models\UserProfile;
use App\Models\UserRoleAssignment;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Intervention\Image\Laravel\Facades\Image;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminManagementController extends Controller
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

    public function updateAccountProfile(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'bio' => ['nullable', 'string'],
            'profile_picture' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $payload = ['bio' => $validated['bio'] ?? null];

        if ($request->hasFile('profile_picture')) {
            if ($user->profile?->profile_picture_path) {
                Storage::disk('public')->delete($user->profile->profile_picture_path);
            }

            $image = Image::decodePath($request->file('profile_picture')->getRealPath())
                ->cover(600, 800)
                ->encodeUsingMediaType('image/jpeg', quality: 90);

            $path = 'profiles/'.uniqid('profile_', true).'.jpg';
            Storage::disk('public')->put($path, (string) $image);

            $payload['profile_picture_path'] = $path;
        }

        UserProfile::query()->updateOrCreate(['user_id' => $user->id], $payload);
        ActivityLogger::log($request, 'admin.account.profile.updated', 'admin', 'Updated account roster profile', $user, ['user_id' => $user->id]);

        return back();
    }

    public function updateAthleteProfile(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($user->hasRole('athlete'), 404);

        $validated = $request->validate([
            'height_cm' => ['nullable', 'numeric', 'min:0'],
            'weight_kg' => ['nullable', 'numeric', 'min:0'],
            'geup' => ['required', Rule::in(['GEUP_1', 'GEUP_2', 'GEUP_3', 'GEUP_4', 'GEUP_5', 'GEUP_6', 'GEUP_7', 'GEUP_8', 'GEUP_9', 'GEUP_10', 'DAN'])],
            'gender' => ['required', Rule::in(['MALE', 'FEMALE'])],
            'bday' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:20'],
            'nik' => ['nullable', 'string', 'max:50'],
            'bpjs' => ['nullable', 'string', 'max:50'],
            'alamat' => ['nullable', 'string'],
            'branch_id' => ['nullable', 'exists:branches,branch_id'],
            'group_id' => ['nullable', 'exists:class_groups,group_id'],
        ]);

        DB::transaction(function () use ($user, $validated): void {
            $athlete = $user->athleteProfile()->first();
            $branchId = $validated['branch_id'] ?? $athlete?->branch_id ?? Branch::query()->value('branch_id');
            $groupId = $validated['group_id'] ?? $athlete?->group_id ?? Group::query()->value('group_id');

            if (! $branchId || ! $groupId) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'branch_id' => 'Create at least one branch and one group before saving an athlete profile.',
                    'group_id' => 'Create at least one branch and one group before saving an athlete profile.',
                ]);
            }

            $user->update([
                'gender' => $validated['gender'],
                'bday' => $validated['bday'] ?? null,
                'phone' => $validated['phone'] ?? null,
            ]);

            $payload = [
                'height_cm' => $validated['height_cm'] ?? 0,
                'weight_kg' => $validated['weight_kg'] ?? 0,
                'geup' => $validated['geup'],
                'alamat' => $validated['alamat'] ?? null,
                'branch_id' => $branchId,
                'group_id' => $groupId,
            ];

            if (array_key_exists('nik', $validated)) {
                $nik = $this->nullableString($validated['nik'] ?? null);
                $payload['nik_hash'] = $nik ? hash('sha256', preg_replace('/\s+/', '', $nik)) : null;
                $payload['nik_ciphertext'] = $nik;
            }

            if (array_key_exists('bpjs', $validated)) {
                $bpjs = $this->nullableString($validated['bpjs'] ?? null);
                $payload['bpjs_hash'] = $bpjs ? hash('sha256', preg_replace('/\s+/', '', $bpjs)) : null;
                $payload['bpjs_ciphertext'] = $bpjs;
            }

            Athlete::query()->updateOrCreate(
                ['id' => $user->id],
                $payload,
            );
        });

        return back();
    }

    public function updateCoachProfile(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($user->hasRole('coach'), 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'specialization' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
        ]);

        Coach::query()->updateOrCreate(
            ['id' => $user->id],
            [
                'status' => $validated['status'],
                'specialization' => $validated['specialization'] ?? null,
                'bio' => $validated['bio'] ?? null,
            ],
        );

        return back();
    }

    public function updateParentProfile(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($user->hasRole('parent'), 404);

        $validated = $request->validate([
            'phone' => ['nullable', 'string', 'max:20'],
            'relation' => ['required', Rule::in(['father', 'mother', 'guardian'])],
            'occupation' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($user, $validated): void {
            $user->update(['phone' => $validated['phone'] ?? null]);

            Parents::query()->updateOrCreate(
                ['id' => $user->id],
                [
                    'relation' => $validated['relation'],
                    'occupation' => $validated['occupation'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                ],
            );
        });

        return back();
    }

    public function storeUserCertification(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'cert_type' => ['required', Rule::in(['BELT', 'REFEREE', 'TRAINER'])],
            'title' => ['required', 'string', 'max:120'],
            'issuer' => ['nullable', 'string', 'max:120'],
            'certified_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:10240'],
        ]);

        $userFile = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('user-files', 'public');

            $userFile = UserFile::query()->create([
                'user_id' => $user->id,
                'file_type' => 'CERTIFICATE',
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
            ]);
        }

        $payload = collect($validated)->except('file')->all();

        if (Schema::hasColumn('user_certifications', 'user_file_id')) {
            $payload['user_file_id'] = $userFile?->id;
        }

        $user->certifications()->create($payload);

        return back();
    }

    public function updateUserCertification(Request $request, User $user, UserCertification $certification): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless((int) $certification->user_id === (int) $user->id, 404);

        $validated = $request->validate([
            'cert_type' => ['required', Rule::in(['BELT', 'REFEREE', 'TRAINER'])],
            'title' => ['required', 'string', 'max:120'],
            'issuer' => ['nullable', 'string', 'max:120'],
            'certified_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:10240'],
        ]);

        $payload = collect($validated)->except('file')->all();

        if ($request->hasFile('file') && Schema::hasColumn('user_certifications', 'user_file_id')) {
            $file = $request->file('file');
            $path = $file->store('user-files', 'public');

            $userFile = UserFile::query()->create([
                'user_id' => $user->id,
                'file_type' => 'CERTIFICATE',
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
            ]);

            $payload['user_file_id'] = $userFile->id;
        }

        $certification->update($payload);

        return back();
    }

    public function storeUserAchievement(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'championship_name' => ['required', 'string', 'max:120'],
            'medal' => ['required', Rule::in(['GOLD', 'SILVER', 'BRONZE', 'NONE'])],
            'location' => ['nullable', 'string', 'max:160'],
            'event_date' => ['nullable', 'date'],
            'class_name' => ['nullable', 'string', 'max:120'],
            'division' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:10240'],
        ]);

        $userFile = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('user-files', 'public');

            $userFile = UserFile::query()->create([
                'user_id' => $user->id,
                'file_type' => 'EVENT_DOCUMENT',
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
            ]);
        }

        $payload = collect($validated)->except('file')->all() + ['is_auto_recorded' => false];

        if (Schema::hasColumn('user_achievements', 'user_file_id')) {
            $payload['user_file_id'] = $userFile?->id;
        }

        $user->achievements()->create($payload);

        return back();
    }

    public function updateUserAchievement(Request $request, User $user, UserAchievement $achievement): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless((int) $achievement->user_id === (int) $user->id, 404);

        $validated = $request->validate([
            'championship_name' => ['required', 'string', 'max:120'],
            'medal' => ['required', Rule::in(['GOLD', 'SILVER', 'BRONZE', 'NONE'])],
            'location' => ['nullable', 'string', 'max:160'],
            'event_date' => ['nullable', 'date'],
            'class_name' => ['nullable', 'string', 'max:120'],
            'division' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:10240'],
        ]);

        $payload = collect($validated)->except('file')->all();

        if ($request->hasFile('file') && Schema::hasColumn('user_achievements', 'user_file_id')) {
            $file = $request->file('file');
            $path = $file->store('user-files', 'public');

            $userFile = UserFile::query()->create([
                'user_id' => $user->id,
                'file_type' => 'EVENT_DOCUMENT',
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
            ]);

            $payload['user_file_id'] = $userFile->id;
        }

        $achievement->update($payload);

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

    public function show(User $user): Response
    {
        abort_unless(request()->user()?->isAdmin(), 403);

        $user->load([
            'profile',
            'athleteProfile.branch',
            'athleteProfile.group',
            'coachProfile',
            'parentProfile.athletes.branch',
            'parentProfile.athletes.group',
            'parentProfile.athletes.user',
            'achievements.file',
            'certifications.file',
            'roleAssignments',
        ]);

        return Inertia::render('profiles/ProfileDetailsPage', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'gender' => $user->gender,
                'bday' => $user->bday?->format('Y-m-d'),
                'phone' => $user->phone,
                'roles' => $user->assignedRoles(),
                'bio' => $user->profile?->bio,
                'profilePictureUrl' => $user->profile?->profile_picture_path ? Storage::url($user->profile->profile_picture_path) : null,
                'athleteProfile' => $user->athleteProfile ? [
                    'height_cm' => $user->athleteProfile->height_cm,
                    'weight_kg' => $user->athleteProfile->weight_kg,
                    'geup' => $user->athleteProfile->geup,
                    'nik' => $user->athleteProfile->displayValue('nik'),
                    'bpjs' => $user->athleteProfile->displayValue('bpjs'),
                    'nikHash' => $user->athleteProfile->nik_hash,
                    'bpjsHash' => $user->athleteProfile->bpjs_hash,
                    'phone' => $user->phone,
                    'bday' => $user->bday?->format('Y-m-d'),
                    'gender' => $user->gender,
                    'alamat' => $user->athleteProfile->alamat,
                    'branch_id' => $user->athleteProfile->branch_id,
                    'group_id' => $user->athleteProfile->group_id,
                    'branch' => $user->athleteProfile->branch,
                    'group' => $user->athleteProfile->group,
                ] : null,
                'coachProfile' => $user->coachProfile ? [
                    'status' => $user->coachProfile->status,
                    'specialization' => $user->coachProfile->specialization,
                    'bio' => $user->coachProfile->bio,
                ] : null,
                'parentProfile' => $user->parentProfile ? [
                    'phone' => $user->phone,
                    'relation' => $user->parentProfile->relation,
                    'occupation' => $user->parentProfile->occupation,
                    'notes' => $user->parentProfile->notes,
                    'athletes' => $user->parentProfile->athletes->map(fn ($athlete) => [
                        'id' => $athlete->athlete_id,
                        'name' => $athlete->user?->name ?? 'Unknown athlete',
                        'branch' => $athlete->branch,
                        'group' => $athlete->group,
                    ]),
                ] : null,
                'achievements' => $user->achievements->map(fn ($achievement) => [
                    'id' => $achievement->id,
                    'championship_name' => $achievement->championship_name,
                    'medal' => $achievement->medal,
                    'location' => $achievement->location,
                    'event_date' => $achievement->event_date?->format('Y-m-d'),
                    'class_name' => $achievement->class_name,
                    'division' => $achievement->division,
                    'category' => $achievement->category,
                    'notes' => $achievement->notes,
                    'fileName' => $achievement->file?->original_name,
                    'fileUrl' => $achievement->file?->file_path ? Storage::url($achievement->file->file_path) : null,
                ]),
                'certifications' => $user->certifications->map(fn ($cert) => [
                    'id' => $cert->id,
                    'cert_type' => $cert->cert_type,
                    'title' => $cert->title,
                    'issuer' => $cert->issuer,
                    'certified_at' => $cert->certified_at?->format('Y-m-d'),
                    'expires_at' => $cert->expires_at?->format('Y-m-d'),
                    'notes' => $cert->notes,
                    'fileName' => $cert->file?->original_name,
                    'fileUrl' => $cert->file?->file_path ? Storage::url($cert->file->file_path) : null,
                ]),
            ],
            'context' => 'admin',
            'canEditAccount' => false,
            'canEditRoleProfiles' => true,
            'branches' => Branch::query()
                ->orderBy('branch_name')
                ->get(['branch_id as value', 'branch_name as label']),
            'groups' => Group::query()
                ->orderBy('group_name')
                ->get(['group_id as value', 'group_name as label']),
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
            Parents::firstOrCreate(
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
                    'password' => Hash::make('TempPass123!'),
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
            'athlete_id' => (int) ($row['athlete_id'] ?? 0),
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
            'coach_id' => $this->nullableInt($row['coach_id'] ?? null),
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
            'athlete_id' => (int) ($row['athlete_id'] ?? 0),
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
            'athlete_id' => (int) ($row['athlete_id'] ?? 0),
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
