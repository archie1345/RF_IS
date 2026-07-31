<?php

namespace App\Services;

use App\Exports\SelectedDataExport;
use App\Models\Attendance;
use App\Models\Athlete;
use App\Models\CoachAttendance;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Payment;
use App\Models\TrainingSession;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AdminDataExportService
{
    /** @return array<int, string> */
    public function datasetKeys(): array
    {
        return array_keys($this->definitions());
    }

    /** @return array<int, array<string, mixed>> */
    public function catalog(): array
    {
        return collect($this->definitions())
            ->map(function (array $definition, string $key): array {
                return [
                    'key' => $key,
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'fields' => collect($definition['fields'])
                        ->map(fn (array $field, string $fieldKey): array => [
                            'key' => $fieldKey,
                            'label' => $field['label'],
                        ])
                        ->values()
                        ->all(),
                    'statusOptions' => $definition['status_options'] ?? [],
                    'roleOptions' => $definition['role_options'] ?? [],
                    'supportsDateRange' => (bool) ($definition['date_column'] ?? false),
                    'supportsDeleted' => (bool) ($definition['supports_deleted'] ?? false),
                    'supportsBranch' => (bool) ($definition['supports_branch'] ?? false),
                    'supportsGroup' => (bool) ($definition['supports_group'] ?? false),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $selectedFields
     * @param  array<string, mixed>  $filters
     */
    public function makeExport(string $dataset, array $selectedFields, array $filters): SelectedDataExport
    {
        $definition = $this->definitions()[$dataset] ?? null;

        if (! $definition) {
            throw ValidationException::withMessages(['dataset' => 'The selected export dataset is not supported.']);
        }

        $fields = $definition['fields'];
        $selected = collect($selectedFields)
            ->map(fn (mixed $field): string => trim((string) $field))
            ->filter(fn (string $field): bool => array_key_exists($field, $fields))
            ->unique()
            ->values();

        if ($selected->isEmpty()) {
            throw ValidationException::withMessages(['fields' => 'Select at least one valid column to export.']);
        }

        /** @var Builder $query */
        $query = ($definition['query'])($filters);
        $headings = $selected->map(fn (string $field): string => $fields[$field]['label'])->all();
        $mapper = function (mixed $record) use ($selected, $fields): array {
            return $selected
                ->map(fn (string $field): mixed => $this->normalizeCell(($fields[$field]['value'])($record)))
                ->all();
        };

        return new SelectedDataExport($query, $headings, $mapper, $definition['sheet']);
    }

    public function filename(string $dataset): string
    {
        $definition = $this->definitions()[$dataset] ?? null;
        $prefix = $definition['filename'] ?? 'selected_data';

        return $prefix.'_'.now()->format('Ymd_His').'.xlsx';
    }

    /** @return array<string, array<string, mixed>> */
    private function definitions(): array
    {
        $accountStatuses = [
            ['value' => User::ACCOUNT_STATUS_ACTIVE, 'label' => 'Active'],
            ['value' => User::ACCOUNT_STATUS_INVITED, 'label' => 'Invited'],
            ['value' => User::ACCOUNT_STATUS_SUSPENDED, 'label' => 'Not active'],
        ];
        $roleOptions = [
            ['value' => 'admin', 'label' => 'Admin'],
            ['value' => 'coach', 'label' => 'Coach'],
            ['value' => 'parent', 'label' => 'Parent'],
            ['value' => 'athlete', 'label' => 'Athlete'],
        ];

        return [
            'accounts' => [
                'label' => 'Accounts',
                'description' => 'Account identity, roles, lifecycle status, branch context, and timestamps.',
                'sheet' => 'Accounts',
                'filename' => 'accounts',
                'status_options' => $accountStatuses,
                'role_options' => $roleOptions,
                'date_column' => 'created_at',
                'supports_deleted' => true,
                'query' => fn (array $filters): Builder => $this->accountsQuery($filters),
                'fields' => [
                    'id' => ['label' => 'Account ID', 'value' => fn (User $user): int => $user->id],
                    'nik' => ['label' => 'NIK', 'value' => fn (User $user): string => $user->displayNik()],
                    'bpjs' => ['label' => 'BPJS', 'value' => fn (User $user): string => $user->displayBpjs()],
                    'name' => ['label' => 'Name', 'value' => fn (User $user): string => (string) $user->name],
                    'email' => ['label' => 'Email', 'value' => fn (User $user): string => (string) $user->email],
                    'phone' => ['label' => 'Phone', 'value' => fn (User $user): string => (string) ($user->phone ?? '')],'gender' => ['label' => 'Gender', 'value' => fn (User $user): string => (string) ($user->gender ?? '')],
                    'birthday' => ['label' => 'Birthday', 'value' => fn (User $user): mixed => $user->bday],
                    'roles' => ['label' => 'Roles', 'value' => fn (User $user): string => implode(', ', $user->assignedRoles())],
                    'status' => ['label' => 'Account Status', 'value' => fn (User $user): string => $this->accountStatusLabel($user->account_status)],
                    'branch' => ['label' => 'Branch', 'value' => fn (User $user): string => $this->accountBranch($user)],
                    'deleted_at' => ['label' => 'Deleted At', 'value' => fn (User $user): mixed => $user->deleted_at],
                    'created_at' => ['label' => 'Created At', 'value' => fn (User $user): mixed => $user->created_at],
                    'updated_at' => ['label' => 'Updated At', 'value' => fn (User $user): mixed => $user->updated_at],
                ],
            ],
            'athletes' => [
                'label' => 'Athletes',
                'description' => 'Athlete profile, account, parent, group, branch, and membership data.',
                'sheet' => 'Athletes',
                'filename' => 'athletes',
                'status_options' => $accountStatuses,
                'date_column' => 'joined_at',
                'supports_deleted' => true,
                'supports_branch' => true,
                'supports_group' => true,
                'query' => fn (array $filters): Builder => $this->athletesQuery($filters),
                'fields' => [
                    'athlete_id' => ['label' => 'Athlete ID', 'value' => fn (Athlete $athlete): string => (string) $athlete->athlete_id],
                    'member_number' => ['label' => 'Member Number', 'value' => fn (Athlete $athlete): string => (string) ($athlete->member_number ?? '')],
                    'name' => ['label' => 'Athlete Name', 'value' => fn (Athlete $athlete): string => (string) ($athlete->user?->name ?? '')],
                    'nik' => ['label' => 'NIK', 'value' => fn (Athlete $athlete): string => $athlete->user?->displayNik() ?? ''],
                    'bpjs' => ['label' => 'BPJS Number', 'value' => fn (Athlete $athlete): string => $athlete->user?->displayBpjs() ?? ''],
                    'email' => ['label' => 'Email', 'value' => fn (Athlete $athlete): string => (string) ($athlete->user?->email ?? '')],
                    'phone' => ['label' => 'Phone', 'value' => fn (Athlete $athlete): string => (string) ($athlete->user?->phone ?? '')],
                    'gender' => ['label' => 'Gender', 'value' => fn (Athlete $athlete): string => (string) ($athlete->user?->gender ?? '')],
                    'birthday' => ['label' => 'Birthday', 'value' => fn (Athlete $athlete): mixed => $athlete->user?->bday],
                    'account_status' => ['label' => 'Account Status', 'value' => fn (Athlete $athlete): string => $this->accountStatusLabel($athlete->user?->account_status)],
                    'branch' => ['label' => 'Branch', 'value' => fn (Athlete $athlete): string => (string) ($athlete->branch?->branch_name ?? '')],
                    'group' => ['label' => 'Group', 'value' => fn (Athlete $athlete): string => (string) ($athlete->group?->group_name ?? '')],
                    'parent' => ['label' => 'Parent Name', 'value' => fn (Athlete $athlete): string => (string) ($athlete->parent?->user?->name ?? '')],
                    'parent_phone' => ['label' => 'Parent Phone', 'value' => fn (Athlete $athlete): string => (string) ($athlete->parent?->user?->phone ?? '')],
                    'geup' => ['label' => 'Belt / Geup', 'value' => fn (Athlete $athlete): string => (string) ($athlete->geup ?? '')],
                    'height_cm' => ['label' => 'Height (cm)', 'value' => fn (Athlete $athlete): float => (float) ($athlete->height_cm ?? 0)],
                    'weight_kg' => ['label' => 'Weight (kg)', 'value' => fn (Athlete $athlete): float => (float) ($athlete->weight_kg ?? 0)],
                    'school_origin' => ['label' => 'School Origin', 'value' => fn (Athlete $athlete): string => (string) ($athlete->school_origin ?? '')],
                    'geup' => ['label' => 'Geup / Belt Rank', 'value' => fn (Athlete $athlete): string => (string) ($athlete->geup ?? '')],
                    'deleted_at' => ['label' => 'Deleted At', 'value' => fn (Athlete $athlete): mixed => $athlete->deleted_at],
                    'joined_at' => ['label' => 'Joined Date', 'value' => fn (Athlete $athlete): string => $this->formatDate($athlete->joined_at)],
                ],
            ],
            'payments' => [
                'label' => 'Payments and Payroll',
                'description' => 'Invoices, member payments, payroll calculations, balances, and proof status.',
                'sheet' => 'Payments',
                'filename' => 'payments_and_payroll',
                'status_options' => [
                    ['value' => 'PENDING', 'label' => 'Pending'],
                    ['value' => 'COMPLETED', 'label' => 'Completed'],
                    ['value' => 'FAILED', 'label' => 'Failed'],
                    ['value' => 'REFUNDED', 'label' => 'Refunded'],
                ],
                'date_column' => 'payment_date',
                'supports_deleted' => true,
                'query' => fn (array $filters): Builder => $this->paymentsQuery($filters),
                'fields' => [
                    'invoice_number' => ['label' => 'Invoice Number', 'value' => fn (Payment $payment): string => (string) $payment->invoice_number],
                    'bill_kind' => ['label' => 'Record Type', 'value' => fn (Payment $payment): string => (string) $payment->bill_kind],
                    'recipient' => ['label' => 'Recipient', 'value' => fn (Payment $payment): string => $this->paymentRecipient($payment)],
                    'payment_type' => ['label' => 'Payment Type', 'value' => fn (Payment $payment): string => (string) $payment->payment_type],
                    'payment_date' => ['label' => 'Issue / Payment Date', 'value' => fn (Payment $payment): mixed => $payment->payment_date],
                    'due_date' => ['label' => 'Due Date', 'value' => fn (Payment $payment): mixed => $payment->due_date],
                    'collection_method' => ['label' => 'Collection Method', 'value' => fn (Payment $payment): string => (string) ($payment->collection_method ?? '')],
                    'total_amount' => ['label' => 'Total Amount', 'value' => fn (Payment $payment): float => (float) ($payment->total_amount ?? $payment->amount ?? 0)],
                    'paid_amount' => ['label' => 'Paid Amount', 'value' => fn (Payment $payment): float => (float) ($payment->paid_amount ?? 0)],
                    'remaining_amount' => ['label' => 'Remaining Amount', 'value' => fn (Payment $payment): float => (float) ($payment->remaining_amount ?? 0)],
                    'status' => ['label' => 'Status', 'value' => fn (Payment $payment): string => (string) $payment->status],
                    'proof_status' => ['label' => 'Proof Status', 'value' => fn (Payment $payment): string => (string) ($payment->proof_status ?? '')],
                    'payroll_period' => ['label' => 'Payroll Period', 'value' => fn (Payment $payment): mixed => $payment->payroll_period],
                    'payroll_basis' => ['label' => 'Payroll Basis', 'value' => fn (Payment $payment): string => (string) ($payment->payroll_basis_type ?? '')],
                    'payroll_units' => ['label' => 'Payroll Units', 'value' => fn (Payment $payment): float => (float) ($payment->payroll_units ?? 0)],
                    'payroll_rate' => ['label' => 'Payroll Rate', 'value' => fn (Payment $payment): float => (float) ($payment->payroll_rate ?? 0)],
                    'payroll_bonus' => ['label' => 'Payroll Bonus', 'value' => fn (Payment $payment): float => (float) ($payment->payroll_bonus_amount ?? 0)],
                    'notes' => ['label' => 'Notes', 'value' => fn (Payment $payment): string => (string) ($payment->notes ?? '')],
                    'created_at' => ['label' => 'Created At', 'value' => fn (Payment $payment): mixed => $payment->created_at],
                ],
            ],
            'athlete_attendance' => [
                'label' => 'Athlete Attendance',
                'description' => 'Athlete attendance status, session, check-in time, notes, and follow-up owner.',
                'sheet' => 'Athlete Attendance',
                'filename' => 'athlete_attendance',
                'status_options' => [
                    ['value' => 'PRESENT', 'label' => 'Present'],
                    ['value' => 'LATE', 'label' => 'Late'],
                    ['value' => 'EXCUSED', 'label' => 'Excused'],
                    ['value' => 'ABSENT', 'label' => 'Absent'],
                ],
                'date_column' => 'date',
                'supports_deleted' => true,
                'query' => fn (array $filters): Builder => $this->athleteAttendanceQuery($filters),
                'fields' => [
                    'date' => ['label' => 'Date', 'value' => fn (Attendance $attendance): mixed => $attendance->date],
                    'athlete' => ['label' => 'Athlete', 'value' => fn (Attendance $attendance): string => (string) ($attendance->athlete?->user?->name ?? '')],
                    'session' => ['label' => 'Session', 'value' => fn (Attendance $attendance): string => (string) ($attendance->trainingSession?->title ?? '')],
                    'session_type' => ['label' => 'Session Type', 'value' => fn (Attendance $attendance): string => (string) ($attendance->trainingSession?->session_type ?? '')],
                    'status' => ['label' => 'Attendance Status', 'value' => fn (Attendance $attendance): string => (string) $attendance->status],
                    'checked_in_at' => ['label' => 'Checked In At', 'value' => fn (Attendance $attendance): mixed => $attendance->checked_in_at],
                    'notes' => ['label' => 'Notes', 'value' => fn (Attendance $attendance): string => (string) ($attendance->notes ?? '')],
                    'follow_up_owner' => ['label' => 'Follow-up Owner', 'value' => fn (Attendance $attendance): string => (string) ($attendance->follow_up_owner ?? '')],
                ],
            ],
            'coach_attendance' => [
                'label' => 'Coach Attendance',
                'description' => 'Coach teaching attendance linked to training sessions and payroll source data.',
                'sheet' => 'Coach Attendance',
                'filename' => 'coach_attendance',
                'status_options' => [
                    ['value' => 'TEACH', 'label' => 'Teaching'],
                    ['value' => 'NOT_TEACH', 'label' => 'Not teaching'],
                ],
                'date_column' => 'checked_at',
                'supports_deleted' => true,
                'query' => fn (array $filters): Builder => $this->coachAttendanceQuery($filters),
                'fields' => [
                    'session_date' => ['label' => 'Session Date', 'value' => fn (CoachAttendance $attendance): mixed => $attendance->trainingSession?->session_date],
                    'coach' => ['label' => 'Coach', 'value' => fn (CoachAttendance $attendance): string => (string) ($attendance->coach?->user?->name ?? '')],
                    'session' => ['label' => 'Session', 'value' => fn (CoachAttendance $attendance): string => (string) ($attendance->trainingSession?->title ?? '')],
                    'status' => ['label' => 'Attendance Status', 'value' => fn (CoachAttendance $attendance): string => (string) $attendance->status],
                    'checked_at' => ['label' => 'Checked At', 'value' => fn (CoachAttendance $attendance): mixed => $attendance->checked_at],
                ],
            ],
            'training_sessions' => [
                'label' => 'Training Sessions',
                'description' => 'Training dates, time, branch, group, coach, private athlete, and session status.',
                'sheet' => 'Training Sessions',
                'filename' => 'training_sessions',
                'status_options' => [
                    ['value' => 'SCHEDULED', 'label' => 'Scheduled'],
                    ['value' => 'ONGOING', 'label' => 'Ongoing'],
                    ['value' => 'COMPLETED', 'label' => 'Completed'],
                    ['value' => 'CANCELLED', 'label' => 'Cancelled'],
                ],
                'date_column' => 'session_date',
                'supports_deleted' => true,
                'query' => fn (array $filters): Builder => $this->trainingSessionsQuery($filters),
                'fields' => [
                    'session_id' => ['label' => 'Session ID', 'value' => fn (TrainingSession $session): int => $session->training_session_id],
                    'date' => ['label' => 'Date', 'value' => fn (TrainingSession $session): mixed => $session->session_date],
                    'title' => ['label' => 'Title', 'value' => fn (TrainingSession $session): string => (string) $session->title],
                    'session_type' => ['label' => 'Session Type', 'value' => fn (TrainingSession $session): string => (string) $session->session_type],
                    'branch' => ['label' => 'Branch', 'value' => fn (TrainingSession $session): string => (string) ($session->branch?->branch_name ?? '')],
                    'group' => ['label' => 'Group', 'value' => fn (TrainingSession $session): string => (string) ($session->group?->group_name ?? '')],
                    'coach' => ['label' => 'Primary Coach', 'value' => fn (TrainingSession $session): string => (string) ($session->primaryCoach?->user?->name ?? '')],
                    'private_athlete' => ['label' => 'Private Athlete', 'value' => fn (TrainingSession $session): string => (string) ($session->dedicatedAthlete?->user?->name ?? '')],
                    'location' => ['label' => 'Location', 'value' => fn (TrainingSession $session): string => (string) ($session->location ?? '')],
                    'start_time' => ['label' => 'Start Time', 'value' => fn (TrainingSession $session): string => (string) ($session->start_time ?? '')],
                    'end_time' => ['label' => 'End Time', 'value' => fn (TrainingSession $session): string => (string) ($session->end_time ?? '')],
                    'status' => ['label' => 'Status', 'value' => fn (TrainingSession $session): string => (string) $session->status],
                ],
            ],
            'events' => [
                'label' => 'Championships and UKT Events',
                'description' => 'Event schedule, deadline, capacity, fee, organizer, and registration count.',
                'sheet' => 'Events',
                'filename' => 'events',
                'status_options' => [
                    ['value' => 'SCHEDULED', 'label' => 'Scheduled'],
                    ['value' => 'ONGOING', 'label' => 'Ongoing'],
                    ['value' => 'COMPLETED', 'label' => 'Completed'],
                    ['value' => 'CANCELLED', 'label' => 'Cancelled'],
                ],
                'date_column' => 'e_date',
                'supports_deleted' => true,
                'query' => fn (array $filters): Builder => $this->eventsQuery($filters),
                'fields' => [
                    'event_id' => ['label' => 'Event ID', 'value' => fn (Event $event): int => $event->event_id],
                    'name' => ['label' => 'Event Name', 'value' => fn (Event $event): string => (string) $event->e_name],
                    'date' => ['label' => 'Event Date', 'value' => fn (Event $event): mixed => $event->e_date],
                    'deadline' => ['label' => 'Registration Deadline', 'value' => fn (Event $event): mixed => $event->registration_deadline],
                    'location' => ['label' => 'Location', 'value' => fn (Event $event): string => (string) ($event->location ?? '')],
                    'level' => ['label' => 'Level', 'value' => fn (Event $event): string => (string) ($event->level ?? '')],
                    'entry_fee' => ['label' => 'Entry Fee', 'value' => fn (Event $event): float => (float) ($event->entry_fee ?? 0)],
                    'max_slots' => ['label' => 'Maximum Slots', 'value' => fn (Event $event): int => (int) ($event->max_slots ?? 0)],
                    'registrations_count' => ['label' => 'Registrations', 'value' => fn (Event $event): int => (int) $event->registrations_count],
                    'organizer' => ['label' => 'Organizer', 'value' => fn (Event $event): string => (string) ($event->organizer ?? '')],
                    'contact_info' => ['label' => 'Contact Information', 'value' => fn (Event $event): string => (string) ($event->contact_info ?? '')],
                    'status' => ['label' => 'Status', 'value' => fn (Event $event): string => (string) $event->status],
                    'description' => ['label' => 'Description', 'value' => fn (Event $event): string => (string) ($event->description ?? '')],
                ],
            ],
            'event_registrations' => [
                'label' => 'Event Participants',
                'description' => 'Athlete registration details and post-event results for championships and UKT.',
                'sheet' => 'Event Participants',
                'filename' => 'event_participants',
                'date_column' => 'registered_at',
                'supports_deleted' => true,
                'query' => fn (array $filters): Builder => $this->eventRegistrationsQuery($filters),
                'fields' => [
                    'registration_id' => ['label' => 'Registration ID', 'value' => fn (EventRegistration $registration): int => $registration->evrid],
                    'event' => ['label' => 'Event', 'value' => fn (EventRegistration $registration): string => (string) ($registration->event?->e_name ?? '')],
                    'event_date' => ['label' => 'Event Date', 'value' => fn (EventRegistration $registration): mixed => $registration->event?->e_date],
                    'athlete' => ['label' => 'Athlete', 'value' => fn (EventRegistration $registration): string => (string) ($registration->athlete?->user?->name ?? '')],
                    'category' => ['label' => 'Category', 'value' => fn (EventRegistration $registration): string => (string) ($registration->category ?? '')],
                    'classification' => ['label' => 'Classification', 'value' => fn (EventRegistration $registration): string => (string) ($registration->classification ?? '')],
                    'class_name' => ['label' => 'Class', 'value' => fn (EventRegistration $registration): string => (string) ($registration->class_name ?? '')],
                    'division' => ['label' => 'Division', 'value' => fn (EventRegistration $registration): string => (string) ($registration->division ?? '')],
                    'contingent' => ['label' => 'Team / Contingent', 'value' => fn (EventRegistration $registration): string => (string) ($registration->team_contingent ?? '')],
                    'status' => ['label' => 'Registration Status', 'value' => fn (EventRegistration $registration): string => (string) ($registration->status ?? '')],
                    'result_medal' => ['label' => 'Result / Medal', 'value' => fn (EventRegistration $registration): string => (string) ($registration->result_medal ?? '')],
                    'result_class' => ['label' => 'Result Class', 'value' => fn (EventRegistration $registration): string => (string) ($registration->result_class_name ?? '')],
                    'result_division' => ['label' => 'Result Division', 'value' => fn (EventRegistration $registration): string => (string) ($registration->result_division ?? '')],
                    'registered_at' => ['label' => 'Registered At', 'value' => fn (EventRegistration $registration): mixed => $registration->registered_at ?? $registration->created_at],
                ],
            ],
        ];
    }

    private function applyBranchAndGroupFilter(
        Builder $query,
        array $filters,
        string $branchCol = 'branch_id',
        string $groupCol = 'group_id'
    ): Builder {
        if (filled($filters['branch'] ?? null)) {
            $branch = $filters['branch'];
            
            if (is_numeric($branch) || str_starts_with($branch, '01') || strlen($branch) === 26) {
                $query->where($branchCol, $branch);
            } else {
                $query->whereHas('branch', fn (Builder $q) => $q->where('branch_name', 'like', "%{$branch}%"));
            }
        }

        if (filled($filters['group'] ?? null)) {
            $group = $filters['group'];
            
            if (is_numeric($group) || str_starts_with($group, '01') || strlen($group) === 26) {
                $query->where($groupCol, $group);
            } else {
                $query->whereHas('group', fn (Builder $q) => $q->where('group_name', 'like', "%{$group}%"));
            }
        }

        return $query;
    }

    /** @param array<string, mixed> $filters */
    private function accountsQuery(array $filters): Builder
    {
        $query = User::query()
            ->with([
                'profile',
                'roleAssignments:id,user_id,role',
                'athleteProfile.branch:branch_id,branch_name',
                'coachProfile',
            ]);

        if ($this->includeDeleted($filters)) {
            $query->withTrashed();
        }
        if (filled($filters['status'] ?? null)) {
            $query->where('account_status', $filters['status']);
        }
        if (filled($filters['role'] ?? null)) {
            $query->withRole((string) $filters['role']);
        }

        return $this->applyDateRange($query, 'created_at', $filters)->orderBy('name')->orderBy('id');
    }

    /** @param array<string, mixed> $filters */
    private function athletesQuery(array $filters): Builder
{
    $query = Athlete::query()->with([
        'user:id,name,email,phone,gender,bday,account_status',
        'user.profile', // FK: user_profiles.user_id -> users.id (for NIK & BPJS)
        'branch:branch_id,branch_name',
        'group:group_id,group_name',
        'parent:parent_id,id',
        'parent.user:id,name,email,phone',
    ]);

    if ($this->includeDeleted($filters)) {
        $query->withTrashed();
    }

    if (filled($filters['status'] ?? null)) {
        $query->whereHas('user', fn (Builder $userQuery): Builder => $userQuery->where('account_status', $filters['status']));
    }

    $this->applyBranchAndGroupFilter($query, $filters, 'branch_id', 'group_id');

    return $this->applyDateRange($query, 'joined_at', $filters)
        ->orderBy('joined_at')
        ->orderBy('athlete_id');
}
    /** @param array<string, mixed> $filters */
    private function paymentsQuery(array $filters): Builder
    {
        $query = Payment::query()->with([
            'athlete.user:id,name,email',
            'billableUser:id,name,email',
            'payeeUser:id,name,email',
        ]);

        if ($this->includeDeleted($filters)) {
            $query->withTrashed();
        }
        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        return $this->applyDateRange($query, 'payment_date', $filters)->orderBy('payment_date')->orderBy('payment_id');
    }

    /** @param array<string, mixed> $filters */
    private function athleteAttendanceQuery(array $filters): Builder
    {
        $query = Attendance::query()->with([
            'athlete.user:id,name',
            'trainingSession:training_session_id,title,session_type',
        ]);

        if ($this->includeDeleted($filters)) {
            $query->withTrashed();
        }
        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        return $this->applyDateRange($query, 'date', $filters)->orderBy('date')->orderBy('athlete_attendance_id');
    }

    /** @param array<string, mixed> $filters */
    private function coachAttendanceQuery(array $filters): Builder
    {
        $query = CoachAttendance::query()->with([
            'coach.user:id,name',
            'trainingSession:training_session_id,title,session_date',
        ]);

        if ($this->includeDeleted($filters)) {
            $query->withTrashed();
        }
        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        return $this->applyDateRange($query, 'checked_at', $filters)->orderBy('checked_at')->orderBy('coach_attendance_id');
    }

    /** @param array<string, mixed> $filters */
    private function trainingSessionsQuery(array $filters): Builder
    {
        $query = TrainingSession::query()->with([
            'branch:branch_id,branch_name',
            'group:group_id,group_name',
            'primaryCoach:coach_id,id',
            'primaryCoach.user:id,name',
            'dedicatedAthlete:athlete_id,id',
            'dedicatedAthlete.user:id,name',
        ]);

        if ($this->includeDeleted($filters)) {
            $query->withTrashed();
        }
        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        return $this->applyDateRange($query, 'session_date', $filters)->orderBy('session_date')->orderBy('start_time');
    }

    /** @param array<string, mixed> $filters */
    private function eventsQuery(array $filters): Builder
    {
        $query = Event::query()->withCount('registrations');

        if ($this->includeDeleted($filters)) {
            $query->withTrashed();
        }
        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        return $this->applyDateRange($query, 'e_date', $filters)->orderBy('e_date')->orderBy('event_id');
    }

    /** @param array<string, mixed> $filters */
    private function eventRegistrationsQuery(array $filters): Builder
    {
        $query = EventRegistration::query()->with([
            'athlete.user:id,name',
            'event:event_id,e_name,e_date',
        ]);

        if ($this->includeDeleted($filters)) {
            $query->withTrashed();
        }

        return $this->applyDateRange($query, 'registered_at', $filters)->orderBy('registered_at')->orderBy('evrid');
    }

    /** @param array<string, mixed> $filters */
    private function applyDateRange(Builder $query, string $column, array $filters): Builder
    {
        if (filled($filters['date_from'] ?? null)) {
            $query->whereDate($column, '>=', $filters['date_from']);
        }
        if (filled($filters['date_to'] ?? null)) {
            $query->whereDate($column, '<=', $filters['date_to']);
        }

        return $query;
    }

    /** @param array<string, mixed> $filters */
    private function includeDeleted(array $filters): bool
    {
        return filter_var($filters['include_deleted'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function paymentRecipient(Payment $payment): string
    {
        return (string) ($payment->payeeUser?->name
            ?? $payment->billableUser?->name
            ?? $payment->athlete?->user?->name
            ?? '');
    }

    private function accountBranch(User $user): string
    {
        if ($user->athleteProfile?->branch?->branch_name) {
            return $user->athleteProfile->branch->branch_name;
        }

        $childBranches = $user->parentProfile?->athletes
            ?->pluck('branch.branch_name')
            ->filter()
            ->unique()
            ->implode(', ');

        if (filled($childBranches)) {
            return $childBranches;
        }

        return $user->coachProfile ? 'Coaching team' : ($user->hasRole('admin') ? 'Head Office' : '');
    }

    private function accountStatusLabel(?string $status): string
    {
        return match ($status) {
            User::ACCOUNT_STATUS_ACTIVE => 'Active',
            User::ACCOUNT_STATUS_INVITED => 'Invited',
            User::ACCOUNT_STATUS_SUSPENDED => 'Not active',
            default => ucfirst((string) $status),
        };
    }

    private function normalizeCell(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value instanceof Collection) {
            $value = $value->implode(', ');
        }

        if (is_array($value)) {
            $value = implode(', ', array_map('strval', $value));
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_string($value) && preg_match('/^[=+\-@]/', ltrim($value)) === 1) {
            return "'".$value;
        }

        return $value ?? '';
    }

    public function getDatePeriodLabel(array $filters): string
    {
        $from = $filters['date_from'] ?? null;
        $to = $filters['date_to'] ?? null;

        if (blank($from) && blank($to)) {
            return 'All Time';
        }

        if (filled($from) && filled($to)) {
            return "{$from} to {$to}";
        }

        if (filled($from)) {
            return "From {$from}";
        }

        return "Until {$to}";
    }
}
