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
use App\Models\UserAchievement;
use App\Models\UserCertification;
use App\Services\ParentChildContextService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use FormatsPresentationData;

    public function __construct(private readonly ParentChildContextService $childContext) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $role = $user?->primaryRole() ?? 'athlete';
        $children = $role === 'parent' ? $this->childContext->sharedChildrenFor($user)->all() : [];
        $activeChild = $role === 'parent' ? $this->childContext->activeChildFor($request, true) : null;

        return Inertia::render('Dashboard', [
            'metrics' => $this->dashboardMetrics($request, $role),
            'activityPreviewRows' => $this->activityPreviewRows(),
            'announcements' => $this->announcements($request, $role),
            'upcomingEvents' => $this->upcomingEvents(),
            'attendanceRows' => $this->attendanceRows($request, $role),
            'paymentRows' => $this->paymentRows($request, $role),
            'medalRows' => $this->medalRows(),
            'profileSummary' => $this->profileSummary($request, $role),
            'children' => $children,
            'activeChild' => $activeChild,
        ]);
    }

    private function dashboardMetrics(Request $request, string $role): array
    {
        $athleteCount = Athlete::count();
        $coachCount = Coach::count();
        $visiblePayments = $this->visiblePaymentsQuery($request, $role);
        $outstandingBalance = (float) (clone $visiblePayments)->sum('remaining_amount');
        $upcomingEvents = Event::query()->whereDate('e_date', '>=', now()->toDateString())->count();
        $attendanceToday = Attendance::query()->whereDate('date', now()->toDateString())->count();

        return match ($role) {
            'admin' => [
                ['label' => 'No. of athletes', 'value' => (string) $athleteCount, 'detail' => 'Active athlete roster', 'tone' => 'success'],
                ['label' => 'No. of coaches', 'value' => (string) $coachCount, 'detail' => 'Registered coach accounts', 'tone' => 'info'],
                ['label' => 'Payment due', 'value' => $this->rupiah($outstandingBalance), 'detail' => 'Outstanding bills', 'tone' => 'warning'],
                ['label' => 'Attendance today', 'value' => (string) $attendanceToday, 'detail' => 'Attendance records created today', 'tone' => 'neutral'],
            ],
            'parent' => [
                ['label' => 'Selected child', 'value' => $request->session()->has('active_child_id') ? 'Chosen' : 'All linked', 'detail' => 'Use the selector when checking one child', 'tone' => 'info'],
                ['label' => 'Outstanding bills', 'value' => $this->rupiah($outstandingBalance), 'detail' => 'Only linked child bills are counted', 'tone' => 'warning'],
                ['label' => 'Upcoming events', 'value' => (string) $upcomingEvents, 'detail' => 'Open events and competitions', 'tone' => 'success'],
            ],
            default => [
                ['label' => 'Attendance entries', 'value' => (string) Attendance::query()->where('status', 'PRESENT')->count(), 'detail' => 'Recorded present sessions', 'tone' => 'success'],
                ['label' => 'Upcoming events', 'value' => (string) $upcomingEvents, 'detail' => 'Scheduled events ahead', 'tone' => 'info'],
                ['label' => 'Unpaid balance', 'value' => $this->rupiah($outstandingBalance), 'detail' => 'Pending bills for this account', 'tone' => 'warning'],
            ],
        };
    }

    private function activityPreviewRows(): array
    {
        return ActivityLog::query()
            ->with('actor:id,name')
            ->latest('id')
            ->limit(8)
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

    private function announcements(Request $request, string $role): array
    {
        $user = $request->user();
        $isAdmin = (bool) $user?->isAdmin();
        $roleTargets = collect($user?->assignedRoles() ?? [$role])
            ->filter()
            ->map(fn (string $role) => strtoupper($role))
            ->push('ALL')
            ->unique()
            ->values();

        return Announcement::query()
            ->with('creator:id,name')
            ->where('is_active', true)
            ->when(! $isAdmin, fn ($q) => $q->whereIn('target_role', $roleTargets))
            ->where(fn ($q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('expire_at')->orWhere('expire_at', '>=', now()))
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (Announcement $announcement) => [
                'id' => 'DANN-'.$announcement->id,
                'title' => $announcement->title,
                'message' => str($announcement->message)->limit(120)->toString(),
                'audience' => $this->targetLabel($announcement->target_role),
                'published' => $announcement->publish_at?->format('d M Y') ?? ($announcement->created_at?->format('d M Y') ?? '-'),
                'status' => $this->badge('Published', 'success'),
            ])
            ->values()
            ->all();
    }

    private function upcomingEvents(): array
    {
        return Event::query()
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
        $query = Attendance::query()->with('athlete.user:id,name')->latest('date')->latest('athlete_attendance_id');

        if ($role === 'parent') {
            $childIds = $this->childContext->visibleChildAthleteIds($request, true, true);
            $query->whereIn('athlete_id', $childIds);
        } elseif ($role === 'athlete') {
            $athleteId = $request->user()?->athleteProfile?->athlete_id;
            $query->when($athleteId, fn ($inner) => $inner->where('athlete_id', $athleteId));
        }
        // elseif ($role === 'coach' && ! $request->user()?->isAdmin()) {
        //     $query->whereHas('trainingSession', fn ($inner) => $inner->where('coach_id', $request->user()?->coachProfile?->coach_id));
        // }

        return $query->limit(8)->get()->map(fn (Attendance $record) => [
            'id' => 'DA-'.$record->athlete_attendance_id,
            'athlete' => $record->athlete?->user?->name ?? 'Unknown',
            'date' => optional($record->date)->format('d M Y') ?? '-',
            'status' => $this->badge((string) $record->status, $record->status === 'PRESENT' ? 'success' : ($record->status === 'EXCUSED' ? 'info' : 'danger')),
        ])->values()->all();
    }

    private function paymentRows(Request $request, string $role): array
    {
        $query = $this->visiblePaymentsQuery($request, $role)
            ->with(['athlete.user:id,name', 'billableUser:id,name', 'payeeUser:id,name'])
            ->latest('payment_date')
            ->latest('payment_id');

        return $query->limit(8)->get()->map(fn (Payment $payment) => [
            'id' => 'DP-'.$payment->payment_id,
            'athlete' => $this->paymentSubject($payment),
            'total' => $this->rupiah((float) ($payment->total_amount ?? $payment->amount ?? 0)),
            'paid' => $this->rupiah((float) ($payment->paid_amount ?? 0)),
            'remaining' => $this->rupiah((float) ($payment->remaining_amount ?? 0)),
            'status' => $this->badge((float) ($payment->remaining_amount ?? 0) <= 0 ? 'Full' : ((float) ($payment->paid_amount ?? 0) > 0 ? 'Partial' : 'Unpaid'), (float) ($payment->remaining_amount ?? 0) <= 0 ? 'success' : ((float) ($payment->paid_amount ?? 0) > 0 ? 'warning' : 'danger')),
        ])->values()->all();
    }

    private function medalRows(): array
    {
        $counts = UserAchievement::query()
            ->selectRaw('medal, COUNT(*) as total')
            ->whereIn('medal', ['GOLD', 'SILVER', 'BRONZE'])
            ->groupBy('medal')
            ->pluck('total', 'medal');

        return [
            ['id' => 'MED-gold', 'type' => 'Gold', 'count' => (string) ($counts['GOLD'] ?? 0)],
            ['id' => 'MED-silver', 'type' => 'Silver', 'count' => (string) ($counts['SILVER'] ?? 0)],
            ['id' => 'MED-bronze', 'type' => 'Bronze', 'count' => (string) ($counts['BRONZE'] ?? 0)],
        ];
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

    private function visiblePaymentsQuery(Request $request, string $role)
    {
        $query = Payment::query();
        $user = $request->user();

        if ($role === 'admin' || $user?->isAdmin()) {
            return $query;
        }

        if ($role === 'parent') {
            $childIds = $this->childContext->visibleChildAthleteIds($request, true, true);
            $childUserIds = $this->childContext->visibleChildUserIds($request, true, true);

            return $query->where(function ($inner) use ($user, $childIds, $childUserIds): void {
                $inner->where('billable_user_id', $user?->id)
                    ->orWhere('payee_user_id', $user?->id)
                    ->when(count($childIds) > 0, fn ($childQuery) => $childQuery->orWhereIn('athlete_id', $childIds))
                    ->when(count($childUserIds) > 0, fn ($childQuery) => $childQuery->orWhereIn('billable_user_id', $childUserIds));
            });
        }

        if ($role === 'athlete') {
            $athleteId = $user?->athleteProfile?->athlete_id;

            return $query->where(function ($inner) use ($user, $athleteId): void {
                $inner->where('billable_user_id', $user?->id)
                    ->when($athleteId, fn ($athleteQuery) => $athleteQuery->orWhere('athlete_id', $athleteId));
            });
        }

        if ($role === 'coach') {
            return $query->where(function ($inner) use ($user): void {
                $inner->where('billable_user_id', $user?->id)
                    ->orWhere('payee_user_id', $user?->id);
            });
        }

        return $query->where('billable_user_id', $user?->id);
    }

    private function targetLabel(string $targetRole): string
    {
        return match ($targetRole) {
            'ADMIN' => 'Admins',
            'COACH' => 'Coaches',
            'PARENT' => 'Parents',
            'ATHLETE' => 'Athletes',
            default => 'Everyone',
        };
    }

    private function paymentSubject(Payment $payment): string
    {
        if (($payment->bill_kind ?? 'INVOICE') === 'PAYROLL') {
            return 'Payroll: '.($payment->payeeUser?->name ?? 'Unknown coach');
        }

        return $payment->athlete?->user?->name
            ?? $payment->billableUser?->name
            ?? 'Unknown user';
    }
}
