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
            'trainingDays' => $this->trainingDays($request, $role),
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

        return $query->limit(30)->get()->map(fn (Attendance $record) => [
            'id' => 'DA-'.$record->athlete_attendance_id,
            'athlete' => $record->athlete?->user?->name ?? 'Unknown',
            'date' => optional($record->date)->format('Y-m-d') ?? '-',
            'status_value' => $record->status,
            'status' => $this->badge((string) $record->status, $record->status === 'PRESENT' ? 'success' : ($record->status === 'EXCUSED' ? 'info' : 'danger')),
        ])->values()->all();
    }

    private function trainingDays(Request $request, string $role): array
    {
        $start = now()->startOfMonth()->subMonth()->toDateString();
        $end = now()->endOfMonth()->addMonth()->toDateString();
        $user = $request->user();

        $query = TrainingSession::query()
            ->with(['branch:branch_id,branch_name', 'group:group_id,group_name'])
            ->whereBetween('session_date', [$start, $end])
            ->where('status', '!=', 'CANCELED');

        if ($role === 'athlete') {
            $athlete = $user?->athleteProfile;
            if (! $athlete) {
                return [];
            }

            $query->where('branch_id', $athlete->branch_id)
                ->where(fn ($inner) => $inner->whereNull('group_id')->orWhere('group_id', $athlete->group_id));
        } elseif ($role === 'parent') {
            $childIds = $this->childContext->visibleChildAthleteIds($request, true, true);
            $children = Athlete::query()->whereIn('athlete_id', $childIds)->get(['branch_id', 'group_id']);

            if ($children->isEmpty()) {
                return [];
            }

            $query->where(function ($outer) use ($children) {
                foreach ($children as $child) {
                    $outer->orWhere(function ($inner) use ($child) {
                        $inner->where('branch_id', $child->branch_id)
                            ->where(fn ($groupQuery) => $groupQuery->whereNull('group_id')->orWhere('group_id', $child->group_id));
                    });
                }
            });
        } elseif ($role === 'coach' && ! $user?->isAdmin()) {
            $coachId = $user?->coachProfile?->coach_id;
            $query->where(fn ($inner) => $inner->where('coach_id', $coachId)->orWhereHas('assignedCoaches', fn ($coachQuery) => $coachQuery->where('coaches.coach_id', $coachId)));
        }

        return $query->orderBy('session_date')->get()->map(fn (TrainingSession $session) => [
            'id' => 'TR-'.$session->training_session_id,
            'date' => optional($session->session_date)->format('Y-m-d'),
            'title' => $session->title,
            'time' => substr((string) $session->start_time, 0, 5).' - '.substr((string) $session->end_time, 0, 5),
            'branch' => $session->branch?->branch_name ?? 'Unassigned',
            'group' => $session->group?->group_name ?? 'All groups',
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

        if ($role === 'parent') {
            $athleteIds = $this->childContext->visibleChildAthleteIds($request, true, true);
            $childUserIds = Athlete::query()->whereIn('athlete_id', $athleteIds)->pluck('id');
            $query->where(function ($inner) use ($athleteIds, $childUserIds, $user) {
                $inner->whereIn('athlete_id', $athleteIds)
                    ->orWhereIn('billable_user_id', $childUserIds)
                    ->orWhere('payer_user_id', $user?->id);
            });
        } elseif ($role === 'athlete') {
            $athleteId = $user?->athleteProfile?->athlete_id;
            $query->where(fn ($inner) => $inner->where('athlete_id', $athleteId)->orWhere('billable_user_id', $user?->id));
        }

        return $query;
    }
}
