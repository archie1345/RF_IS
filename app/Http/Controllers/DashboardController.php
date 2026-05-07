<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FormatsMvpData;
use App\Models\ActivityLog;
use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Coach;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Session;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use FormatsMvpData;

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $role = $user?->role ?? 'athlete';

        return Inertia::render('Dashboard', [
            'metrics' => $this->dashboardMetrics($request, $role),
            'snapshotRows' => $this->snapshotRows(),
            'activityPreviewRows' => $this->activityPreviewRows(),
            'announcements' => $this->announcements($role),
            'upcomingEvents' => $this->upcomingEvents(),
            'attendanceRows' => $this->attendanceRows($request, $role),
            'paymentRows' => $this->paymentRows($request, $role),
            'medalRows' => $this->medalRows(),
            'profileSummary' => $this->profileSummary($request, $role),
        ]);
    }

    private function dashboardMetrics(Request $request, string $role): array
    {
        $athleteCount = Athlete::count();
        $coachCount = Coach::count();
        $outstandingBalance = (float) Payment::sum('remaining_amount');
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
                ['label' => 'Selected child', 'value' => $request->session()->has('active_child_id') ? 'Chosen' : 'Not selected', 'detail' => 'Pick child from top selector', 'tone' => 'info'],
                ['label' => 'Outstanding bills', 'value' => $this->rupiah((float) Payment::query()->where('remaining_amount', '>', 0)->sum('remaining_amount')), 'detail' => 'For linked child records', 'tone' => 'warning'],
                ['label' => 'Upcoming events', 'value' => (string) $upcomingEvents, 'detail' => 'Open events and competitions', 'tone' => 'success'],
            ],
            default => [
                ['label' => 'Attendance entries', 'value' => (string) Attendance::query()->where('status', 'PRESENT')->count(), 'detail' => 'Recorded present sessions', 'tone' => 'success'],
                ['label' => 'Upcoming events', 'value' => (string) $upcomingEvents, 'detail' => 'Scheduled events ahead', 'tone' => 'info'],
                ['label' => 'Unpaid balance', 'value' => $this->rupiah((float) Payment::sum('remaining_amount')), 'detail' => 'Pending bills', 'tone' => 'warning'],
            ],
        };
    }

    private function snapshotRows(): array
    {
        return [
            ['id' => 'OPS-athletes', 'module' => 'Athletes', 'status' => $this->badge(Athlete::count() > 0 ? 'Connected' : 'Needs data', Athlete::count() > 0 ? 'success' : 'warning'), 'value' => Athlete::count().' records'],
            ['id' => 'OPS-attendance', 'module' => 'Attendance', 'status' => $this->badge(Attendance::count() > 0 ? 'Healthy' : 'Empty', Attendance::count() > 0 ? 'success' : 'warning'), 'value' => Attendance::count().' rows'],
            ['id' => 'OPS-events', 'module' => 'Events', 'status' => $this->badge(Event::count() > 0 ? 'Active' : 'Empty', Event::count() > 0 ? 'info' : 'neutral'), 'value' => Event::count().' events'],
            ['id' => 'OPS-payments', 'module' => 'Payments', 'status' => $this->badge(Payment::sum('remaining_amount') > 0 ? 'Partial' : 'Clear', Payment::sum('remaining_amount') > 0 ? 'warning' : 'success'), 'value' => $this->rupiah((float) Payment::sum('remaining_amount'))],
        ];
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

    private function announcements(string $role): array
    {
        $roleLabel = ucfirst($role);

        return [
            "$roleLabel dashboard now uses reusable dynamic tables.",
            'Attendance session default status is absent for all athletes.',
            'Event payment tracking supports partial and full payment.',
        ];
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
        $query = Attendance::query()->with('athlete.user:id,name')->latest('date')->latest('atid');

        if ($role === 'parent') {
            $childIds = $request->user()?->children()->pluck('athletes.athlete_id')->all() ?? [];
            if ($request->session()->has('active_child_id')) {
                $childIds = [(int) $request->session()->get('active_child_id')];
            }
            $query->whereIn('athlete_id', $childIds);
        }

        return $query->limit(8)->get()->map(fn (Attendance $record) => [
            'id' => 'DA-'.$record->atid,
            'athlete' => $record->athlete?->user?->name ?? 'Unknown',
            'date' => optional($record->date)->format('d M Y') ?? '-',
            'status' => $this->badge((string) $record->status, $record->status === 'PRESENT' ? 'success' : ($record->status === 'EXCUSED' ? 'info' : 'danger')),
        ])->values()->all();
    }

    private function paymentRows(Request $request, string $role): array
    {
        $query = Payment::query()->with('athlete.user:id,name')->latest('payment_date')->latest('payment_id');

        if ($role === 'parent') {
            $childIds = $request->user()?->children()->pluck('athletes.athlete_id')->all() ?? [];
            if ($request->session()->has('active_child_id')) {
                $childIds = [(int) $request->session()->get('active_child_id')];
            }
            $query->whereIn('athlete_id', $childIds);
        }

        return $query->limit(8)->get()->map(fn (Payment $payment) => [
            'id' => 'DP-'.$payment->payment_id,
            'athlete' => $payment->athlete?->user?->name ?? 'Unknown',
            'total' => $this->rupiah((float) ($payment->total_amount ?? $payment->amount ?? 0)),
            'paid' => $this->rupiah((float) ($payment->paid_amount ?? 0)),
            'remaining' => $this->rupiah((float) ($payment->remaining_amount ?? 0)),
            'status' => $this->badge((float) ($payment->remaining_amount ?? 0) <= 0 ? 'Full' : ((float) ($payment->paid_amount ?? 0) > 0 ? 'Partial' : 'Unpaid'), (float) ($payment->remaining_amount ?? 0) <= 0 ? 'success' : ((float) ($payment->paid_amount ?? 0) > 0 ? 'warning' : 'danger')),
        ])->values()->all();
    }

    private function medalRows(): array
    {
        return [
            ['id' => 'MED-gold', 'type' => 'Gold', 'count' => '0'],
            ['id' => 'MED-silver', 'type' => 'Silver', 'count' => '0'],
            ['id' => 'MED-bronze', 'type' => 'Bronze', 'count' => '0'],
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
        ];
    }
}
