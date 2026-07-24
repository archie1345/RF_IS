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
        $role = $this->activeRoleContext->activeRole($request, $user);
        $children = $role === 'parent' ? $this->childContext->sharedChildrenFor($user)->all() : [];
        $activeChild = $role === 'parent' ? $this->childContext->activeChildFor($request, true) : null;

        return Inertia::render('Dashboard', [
            'metrics' => $this->dashboardMetrics($request, $role),
            'activityPreviewRows' => $role === 'admin' ? $this->activityPreviewRows() : [],
            'announcements' => $this->announcements($role),
            'upcomingEvents' => $this->upcomingEvents(),
            'attendanceRows' => $this->attendanceRows($request, $role),
            'trainingDays' => $this->trainingDays($request, $role),
            'paymentRows' => $this->paymentRows($request, $role),
            'medalRows' => $role === 'admin' ? $this->beltDistributionRows() : [],
            'profileSummary' => $this->profileSummary($request, $role),
            'children' => $children,
            'activeChild' => $activeChild,
        ]);
    }

    private function dashboardMetrics(Request $request, string $role): array
    {
        $visiblePayments = $this->paymentVisibility->visiblePaymentsQuery($request, $role);
        $visibleAttendance = $this->attendanceVisibility->scopedAttendanceQuery($request, $role);
        $outstandingBalance = (float) (clone $visiblePayments)
            ->where('remaining_amount', '>', 0)
            ->sum('remaining_amount');
        $upcomingEvents = Event::query()
            ->whereIn('status', ['SCHEDULED', 'ONGOING'])
            ->whereDate('e_date', '>=', now()->toDateString())
            ->count();
        $presentAttendance = (clone $visibleAttendance)->where('status', 'PRESENT')->count();

        return match ($role) {
            'admin' => [
                ['label' => 'No. of athletes', 'value' => (string) Athlete::query()->count(), 'detail' => 'Active athlete roster', 'tone' => 'success'],
                ['label' => 'No. of coaches', 'value' => (string) Coach::query()->where('status', 'active')->count(), 'detail' => 'Active coach profiles', 'tone' => 'info'],
                ['label' => 'Payment due', 'value' => $this->rupiah($outstandingBalance), 'detail' => 'Outstanding bills', 'tone' => 'warning'],
                ['label' => 'Attendance today', 'value' => (string) (clone $visibleAttendance)->whereDate('date', now()->toDateString())->count(), 'detail' => 'Attendance records created today', 'tone' => 'neutral'],
            ],
            'parent' => [
                ['label' => 'Selected child', 'value' => $request->session()->has('active_child_id') ? 'Chosen' : 'All linked', 'detail' => 'Use the selector when checking one child', 'tone' => 'info'],
                ['label' => 'Outstanding bills', 'value' => $this->rupiah($outstandingBalance), 'detail' => 'Only linked child bills are counted', 'tone' => 'warning'],
                ['label' => 'Upcoming events', 'value' => (string) $upcomingEvents, 'detail' => 'Open events and competitions', 'tone' => 'success'],
            ],
            'coach' => [
                ['label' => 'Session attendance', 'value' => (string) $presentAttendance, 'detail' => 'Present athlete records from assigned sessions', 'tone' => 'success'],
                ['label' => 'Upcoming events', 'value' => (string) $upcomingEvents, 'detail' => 'Scheduled events ahead', 'tone' => 'info'],
                ['label' => 'Remaining payroll', 'value' => $this->rupiah($outstandingBalance), 'detail' => 'Outstanding payout assigned to this coach account', 'tone' => 'warning'],
            ],
            default => [
                ['label' => 'Attendance entries', 'value' => (string) $presentAttendance, 'detail' => 'Recorded present sessions for this athlete', 'tone' => 'success'],
                ['label' => 'Upcoming events', 'value' => (string) $upcomingEvents, 'detail' => 'Scheduled events ahead', 'tone' => 'info'],
                ['label' => 'Unpaid balance', 'value' => $this->rupiah($outstandingBalance), 'detail' => 'Pending bills for this athlete account', 'tone' => 'warning'],
            ],
        };
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

    private function announcements(string $role): array
    {
        $isAdmin = $role === 'admin';
        $roleTargets = [strtoupper($role), 'ALL'];

        return Announcement::query()
            ->with('creator:id,name')
            ->where('is_active', true)
            ->when(! $isAdmin, fn ($query) => $query->whereIn('target_role', $roleTargets))
            ->where(fn ($query) => $query->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('expire_at')->orWhere('expire_at', '>=', now()))
            ->latest('publish_at')
            ->latest('id')
            ->limit(3)
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
            ->limit(5)
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
                    $record->status === 'PRESENT' ? 'success' : ($record->status === 'EXCUSED' ? 'info' : 'danger'),
                ),
            ])
            ->values()
            ->all();
    }

    private function trainingDays(Request $request, string $role): array
    {
        $start = now()->startOfMonth()->subMonth()->toDateString();
        $end = now()->endOfMonth()->addMonth()->toDateString();

        return $this->attendanceVisibility
            ->visibleSessionQuery($request->user(), $role)
            ->with(['branch:branch_id,branch_name', 'group:group_id,group_name'])
            ->whereBetween('session_date', [$start, $end])
            ->where('status', '!=', 'CANCELED')
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get()
            ->map(fn (TrainingSession $session) => [
                'id' => 'TR-'.$session->training_session_id,
                'date' => optional($session->session_date)->format('Y-m-d'),
                'title' => $session->title,
                'time' => substr((string) $session->start_time, 0, 5).' - '.substr((string) $session->end_time, 0, 5),
                'branch' => $session->branch?->branch_name ?? 'Unassigned',
                'group' => $session->group?->group_name ?? 'All groups',
            ])
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
            ->limit(8)
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

    private function paymentSubject(Payment $payment): string
    {
        return $payment->athlete?->user?->name
            ?? $payment->billableUser?->name
            ?? $payment->payeeUser?->name
            ?? 'Unassigned bill';
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
