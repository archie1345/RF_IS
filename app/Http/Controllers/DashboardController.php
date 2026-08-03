<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FormatsPresentationData;
use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Coach;
use App\Models\Event;
use App\Models\Payment;
use App\Models\TrainingSession;
use App\Models\UserCertification;
use App\Services\ActiveRoleContextService;
use App\Services\AttendanceVisibilityService;
use App\Services\ParentChildContextService;
use App\Services\PaymentVisibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use FormatsPresentationData;

    public function __construct(
        private readonly ActiveRoleContextService $activeRoleContext,
        private readonly ParentChildContextService $childContext,
        private readonly AttendanceVisibilityService $attendanceVisibility,
        private readonly PaymentVisibilityService $paymentVisibility,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $activeRole = $this->activeRoleContext->activeRole($request, $user);
        $roles = $this->activeRoleContext->availableRoles($user);
        if ($roles === []) {
            $roles = [$activeRole];
        }
        $isMultiRole = count($roles) > 1;

        $metrics = collect($roles)
            ->flatMap(function (string $role) use ($request, $isMultiRole): array {
                $items = $this->dashboardMetrics($request, $role);
                if ($isMultiRole) {
                    $items = array_slice($items, 0, 2);
                    $prefix = $this->roleLabel($role).' · ';
                    $items = array_map(fn (array $metric): array => [
                        ...$metric,
                        'label' => $prefix.$metric['label'],
                    ], $items);
                }

                return $items;
            })
            ->values()
            ->all();

        return Inertia::render('Dashboard', [
            'roles' => $roles,
            'metrics' => $metrics,
            'activityPreviewRows' => in_array('admin', $roles, true) ? $this->activityPreviewRows() : [],
            'announcements' => $this->announcements($roles),
            'upcomingEvents' => $this->upcomingEvents(),
            'attendanceRows' => $this->combinedRows($roles, fn (string $role): array => $this->attendanceRows($request, $role), 30),
            'trainingDays' => $this->combinedRows($roles, fn (string $role): array => $this->trainingDays($request, $role), 30),
            'paymentRows' => $this->combinedRows($roles, fn (string $role): array => $this->paymentRows($request, $role), 16),
            'medalRows' => in_array('admin', $roles, true) ? $this->beltDistributionRows() : [],
            'profileSummary' => in_array('athlete', $roles, true) ? $this->profileSummary($request, 'athlete') : [],
            'children' => in_array('parent', $roles, true) ? $this->childContext->sharedChildrenFor($user)->all() : [],
            'activeChild' => null,
        ]);
    }

    private function dashboardMetrics(Request $request, string $role): array
    {
        $visiblePayments = $this->paymentVisibility->visiblePaymentsQuery($request, $role);
        $visibleAttendance = $this->attendanceVisibility->scopedAttendanceQuery($request, $role);
        $outstandingBalance = (float) (clone $visiblePayments)->where('remaining_amount', '>', 0)->sum('remaining_amount');
        $upcomingEvents = Event::query()
            ->whereIn('status', ['SCHEDULED', 'ONGOING'])
            ->whereDate('e_date', '>=', now()->toDateString())
            ->count();
        $presentAttendance = (clone $visibleAttendance)->where('status', 'PRESENT')->count();

        if ($role === 'admin') {
            $monthStart = now(config('app.timezone', 'Asia/Jakarta'))->startOfMonth();
            $payrollCount = Payment::query()
                ->where('bill_kind', 'PAYROLL')
                ->where(function ($query) use ($monthStart): void {
                    $query->whereBetween('payroll_period', [$monthStart->toDateString(), $monthStart->copy()->endOfMonth()->toDateString()])
                        ->orWhere(function ($fallback) use ($monthStart): void {
                            $fallback->whereNull('payroll_period')
                                ->whereBetween('payment_date', [$monthStart->toDateString(), $monthStart->copy()->endOfMonth()->toDateString()]);
                        });
                })
                ->count();

            return [
                [
                    'label' => 'Payroll '.$monthStart->translatedFormat('F'),
                    'value' => $payrollCount > 0 ? (string) $payrollCount : 'Belum dibuat',
                    'detail' => $payrollCount > 0 ? 'Slip payroll sudah diterbitkan bulan ini' : 'Buka Payroll Pelatih dan buat bukti pembayaran bulan ini',
                    'tone' => $payrollCount > 0 ? 'success' : 'danger',
                ],
                ['label' => 'Atlet aktif', 'value' => (string) Athlete::query()->count(), 'detail' => 'Roster atlet klub', 'tone' => 'success'],
                ['label' => 'Pelatih aktif', 'value' => (string) Coach::query()->where('status', 'active')->count(), 'detail' => 'Profil pelatih aktif', 'tone' => 'info'],
                ['label' => 'Tagihan terbuka', 'value' => $this->rupiah($outstandingBalance), 'detail' => 'Saldo yang belum diselesaikan', 'tone' => 'warning'],
            ];
        }

        if ($role === 'parent') {
            $childCount = $this->childContext->childrenFor($request->user())->count();

            return [
                ['label' => 'Anak terhubung', 'value' => (string) $childCount, 'detail' => 'Semua anak tampil bersama dan dapat difilter', 'tone' => 'info'],
                ['label' => 'Tagihan keluarga', 'value' => $this->rupiah($outstandingBalance), 'detail' => 'Saldo terbuka seluruh anak', 'tone' => 'warning'],
                ['label' => 'Agenda mendatang', 'value' => (string) $upcomingEvents, 'detail' => 'Kejuaraan dan kegiatan tersedia', 'tone' => 'success'],
            ];
        }

        if ($role === 'coach') {
            return [
                ['label' => 'Kehadiran sesi', 'value' => (string) $presentAttendance, 'detail' => 'Atlet hadir dari sesi yang ditangani', 'tone' => 'success'],
                ['label' => 'Agenda mendatang', 'value' => (string) $upcomingEvents, 'detail' => 'Kejuaraan dan kegiatan terjadwal', 'tone' => 'info'],
                ['label' => 'Payroll tersisa', 'value' => $this->rupiah($outstandingBalance), 'detail' => 'Sisa pembayaran pelatih', 'tone' => 'warning'],
            ];
        }

        return [
            ['label' => 'Kehadiran saya', 'value' => (string) $presentAttendance, 'detail' => 'Sesi dengan status hadir', 'tone' => 'success'],
            ['label' => 'Agenda mendatang', 'value' => (string) $upcomingEvents, 'detail' => 'Kejuaraan dan kegiatan terjadwal', 'tone' => 'info'],
            ['label' => 'Tagihan saya', 'value' => $this->rupiah($outstandingBalance), 'detail' => 'Saldo pembayaran yang belum selesai', 'tone' => 'warning'],
        ];
    }

    private function activityPreviewRows(): array
    {
        return ActivityLog::query()
            ->with('actor:id,name')
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id' => 'LOG-'.$log->id,
                'time' => $log->created_at?->format('d M Y H:i') ?? '-',
                'actor' => $log->actor?->name ?? 'System',
                'action' => $log->action,
                'context' => $log->context,
                'description' => $log->description,
            ])
            ->values()
            ->all();
    }

    private function announcements(array $roles): array
    {
        $isAdmin = in_array('admin', $roles, true);
        $roleTargets = collect($roles)->map(fn (string $role): string => strtoupper($role))->push('ALL')->unique()->all();

        return Announcement::query()
            ->with('creator:id,name')
            ->where('is_active', true)
            ->when(! $isAdmin, fn ($query) => $query->whereIn('target_role', $roleTargets))
            ->where(fn ($query) => $query->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('expire_at')->orWhere('expire_at', '>=', now()))
            ->latest('publish_at')
            ->latest('id')
            ->limit(4)
            ->get()
            ->map(fn (Announcement $announcement) => [
                'id' => 'DANN-'.$announcement->id,
                'announcement_id' => $announcement->id,
                'title' => $announcement->title,
                'message' => str($announcement->message)->limit(180)->toString(),
                'target' => $this->targetLabel($announcement->target_role),
                'audience' => $this->targetLabel($announcement->target_role),
                'target_role' => $announcement->target_role,
                'author' => $announcement->creator?->name ?? 'Sistem',
                'published' => $announcement->publish_at?->format('d M Y H:i')
                    ?? ($announcement->created_at?->format('d M Y H:i') ?? '-'),
                'status' => $this->badge('Diterbitkan', 'success'),
            ])
            ->values()
            ->all();
    }

    private function upcomingEvents(): array
    {
        return Event::query()
            ->whereIn('status', ['SCHEDULED', 'ONGOING'])
            ->whereDate('e_date', '>=', now()->toDateString())
            ->orderBy('e_date')
            ->limit(8)
            ->get()
            ->map(fn (Event $event) => [
                'id' => 'UP-'.$event->event_id,
                'event' => $event->e_name,
                'date' => optional($event->e_date)->format('d M Y') ?? '-',
                'location' => $event->location ?? 'TBD',
            ])
            ->values()
            ->all();
    }

    private function attendanceRows(Request $request, string $role): array
    {
        return $this->attendanceVisibility
            ->scopedAttendanceQuery($request, $role)
            ->with('athlete.user:id,name')
            ->latest('date')
            ->latest('athlete_attendance_id')
            ->limit(30)
            ->get()
            ->map(fn (Attendance $record) => [
                'id' => 'DA-'.$record->athlete_attendance_id,
                'athlete' => $record->athlete?->user?->name ?? 'Unknown',
                'date' => optional($record->date)->format('Y-m-d') ?? '-',
                'status_value' => $record->status,
                'status' => $this->badge(
                    (string) $record->status,
                    match ($record->status) {
                        'PRESENT' => 'success',
                        'EXCUSED', 'LATE' => 'info',
                        default => 'danger',
                    },
                ),
            ])
            ->values()
            ->all();
    }

    private function trainingDays(Request $request, string $role): array
    {
        $start = now()->startOfMonth()->subMonth()->toDateString();
        $end = now()->endOfMonth()->addMonth()->toDateString();
        $children = $role === 'parent' ? $this->childContext->childrenFor($request->user()) : collect();

        return $this->attendanceVisibility
            ->visibleSessionQuery($request->user(), $role)
            ->with([
                'branch:branch_id,branch_name',
                'group:group_id,group_name',
                'group.privateAthletes.user:id,name',
                'dedicatedAthlete.user:id,name',
            ])
            ->whereBetween('session_date', [$start, $end])
            ->where('status', '!=', 'CANCELED')
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get()
            ->map(function (TrainingSession $session) use ($children, $role): array {
                $childNames = $role === 'parent'
                    ? $children->filter(fn (Athlete $athlete): bool => $this->athleteMatchesSession($athlete, $session))
                        ->map(fn (Athlete $athlete): string => $athlete->user?->name ?? 'Atlet')
                        ->values()
                    : collect();
                $privateNames = collect([$session->dedicatedAthlete?->user?->name])
                    ->merge($session->group?->privateAthletes?->map(fn (Athlete $athlete) => $athlete->user?->name) ?? collect())
                    ->filter()
                    ->unique();

                return [
                    'id' => 'TR-'.$session->training_session_id,
                    'date' => optional($session->session_date)->format('Y-m-d'),
                    'title' => $session->title,
                    'time' => substr((string) $session->start_time, 0, 5).' - '.substr((string) $session->end_time, 0, 5),
                    'branch' => $session->branch?->branch_name ?? 'Unassigned',
                    'group' => $session->group?->group_name ?? 'All groups',
                    'child' => $childNames->join(', ') ?: '-',
                    'athletes' => $privateNames->join(', ') ?: '-',
                ];
            })
            ->values()
            ->all();
    }

    private function paymentRows(Request $request, string $role): array
    {
        return $this->paymentVisibility
            ->visiblePaymentsQuery($request, $role)
            ->with(['athlete.user:id,name', 'billableUser:id,name', 'payeeUser:id,name'])
            ->latest('payment_date')
            ->latest('payment_id')
            ->limit(16)
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => 'DP-'.$payment->payment_id,
                'athlete' => $this->paymentSubject($payment),
                'total' => $this->rupiah((float) ($payment->total_amount ?? $payment->amount ?? 0)),
                'paid' => $this->rupiah((float) ($payment->paid_amount ?? 0)),
                'remaining' => $this->rupiah((float) ($payment->remaining_amount ?? 0)),
                'status' => $this->badge(
                    (float) ($payment->remaining_amount ?? 0) <= 0
                        ? 'Full'
                        : ((float) ($payment->paid_amount ?? 0) > 0 ? 'Partial' : 'Unpaid'),
                    (float) ($payment->remaining_amount ?? 0) <= 0
                        ? 'success'
                        : ((float) ($payment->paid_amount ?? 0) > 0 ? 'warning' : 'danger'),
                ),
            ])
            ->values()
            ->all();
    }

    private function beltDistributionRows(): array
    {
        return Athlete::query()
            ->selectRaw("COALESCE(NULLIF(TRIM(geup), ''), 'Belum diisi') as belt, COUNT(*) as total")
            ->groupBy('belt')
            ->orderBy('belt')
            ->get()
            ->map(fn ($row) => [
                'id' => 'BELT-'.str($row->belt)->slug()->upper(),
                'type' => $row->belt,
                'count' => (string) $row->total,
            ])
            ->values()
            ->all();
    }

    private function profileSummary(Request $request, string $role): array
    {
        if ($role !== 'athlete') {
            return [];
        }

        $athlete = $request->user()?->athleteProfile;

        return [
            'geup' => $athlete?->geup ?? '-',
            'height' => $athlete?->height_cm ? $athlete->height_cm.' cm' : '-',
            'weight' => $athlete?->weight_kg ? $athlete->weight_kg.' kg' : '-',
            'certifications' => (string) UserCertification::query()->where('user_id', $request->user()?->id)->count(),
        ];
    }

    private function combinedRows(array $roles, callable $resolver, int $limit): array
    {
        return collect($roles)
            ->flatMap(fn (string $role): array => $resolver($role))
            ->unique('id')
            ->take($limit)
            ->values()
            ->all();
    }

    private function athleteMatchesSession(Athlete $athlete, TrainingSession $session): bool
    {
        if ((string) $athlete->branch_id !== (string) $session->branch_id) {
            return false;
        }
        if ($session->dedicated_athlete_id !== null) {
            return (string) $athlete->athlete_id === (string) $session->dedicated_athlete_id;
        }
        if ($session->group_id === null) {
            return true;
        }
        if ((string) $athlete->group_id === (string) $session->group_id) {
            return true;
        }

        return $session->group?->privateAthletes?->contains(
            fn (Athlete $privateAthlete): bool => (string) $privateAthlete->athlete_id === (string) $athlete->athlete_id,
        ) ?? false;
    }

    private function paymentSubject(Payment $payment): string
    {
        return $payment->athlete?->user?->name
            ?? $payment->billableUser?->name
            ?? $payment->payeeUser?->name
            ?? 'Unassigned bill';
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'admin' => 'Admin',
            'coach' => 'Pelatih',
            'parent' => 'Orang tua',
            default => 'Atlet',
        };
    }

    private function targetLabel(?string $target): string
    {
        return match (strtoupper((string) $target)) {
            'ADMIN' => 'Admin',
            'ATHLETE' => 'Atlet',
            'COACH' => 'Pelatih',
            'PARENT' => 'Orang tua',
            default => 'Semua pengguna',
        };
    }
}
