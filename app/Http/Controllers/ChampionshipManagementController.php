<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FormatsMvpData;
use App\Models\Athlete;
use App\Models\Coach;
use App\Models\Event;
use App\Models\EventCoachRegistration;
use App\Models\EventRegistration;
use App\Models\Payment;
use App\Models\UserAchievement;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ChampionshipManagementController extends Controller
{
    use FormatsMvpData;

    public function index(): Response
    {
        $user = auth()->user();
        $events = Event::query()
            ->withCount(['registrations' => fn ($query) => $query->whereNull('deleted_at')])
            ->orderBy('e_date')
            ->get();

        $registrations = EventRegistration::query()->get();

        $athleteOptions = Athlete::query()
            ->with('user:id,name')
            ->get()
            ->map(fn (Athlete $athlete) => ['value' => $athlete->athlete_id, 'label' => $athlete->user?->name ?? 'Unknown athlete'])
            ->sortBy('label')
            ->values();

        if ($user?->isAthlete()) {
            $athleteId = $user->athleteProfile?->athlete_id;
            $athleteOptions = $athleteOptions->filter(fn ($item) => (int) $item['value'] === (int) $athleteId)->values();
        }

        if ($user?->isParent()) {
            $childIds = $user->children()->pluck('athletes.athlete_id')->all();
            $athleteOptions = $athleteOptions->filter(fn ($item) => in_array((int) $item['value'], array_map('intval', $childIds), true))->values();
        }

        $pendingAthleteIds = $athleteOptions->pluck('value')->map(fn ($id) => (int) $id)->all();
        $pendingPayments = Payment::query()
            ->where('payment_type', 'CHAMPIONSHIP')
            ->whereIn('athlete_id', $pendingAthleteIds)
            ->where('remaining_amount', '>', 0)
            ->with('athlete.user:id,name')
            ->latest('payment_date')
            ->get()
            ->map(fn (Payment $payment) => [
                'payment_id' => $payment->payment_id,
                'athlete' => $payment->athlete?->user?->name ?? 'Unknown athlete',
                'amount' => (float) ($payment->total_amount ?? $payment->amount ?? 0),
                'remaining' => (float) ($payment->remaining_amount ?? 0),
            ])
            ->values();

        return Inertia::render('ChampionshipsPage', [
            'isAdmin' => auth()->user()?->isAdmin() ?? false,
            'canRegister' => $user?->isAdmin() || $user?->isParent() || $user?->isAthlete(),
            'metrics' => [
                ['label' => 'Open registrations', 'value' => (string) $events->where('status', 'SCHEDULED')->count(), 'detail' => 'Published events currently taking entries', 'tone' => 'warning'],
                ['label' => 'Athletes submitted', 'value' => (string) $registrations->count(), 'detail' => 'Total registration records created', 'tone' => 'info'],
                ['label' => 'Confirmed entries', 'value' => (string) $registrations->where('status', 'CONFIRMED')->count(), 'detail' => 'Approved registrations ready for travel', 'tone' => 'success'],
            ],
            'rows' => $events->map(fn (Event $event) => [
                'id' => 'EVT-'.$event->event_id,
                'event_id' => $event->event_id,
                'event' => $event->e_name,
                'date' => Carbon::parse($event->e_date)->format('d M Y'),
                'location' => $event->location ?? 'TBD',
                'registration' => $this->eventBadge($event),
                'payment' => $this->eventPaymentBadge($event),
                'slots' => $event->registrations_count.' / '.$event->max_slots.' athletes',
            ])->values(),
            'athletes' => $athleteOptions,
            'events' => $events->map(fn (Event $event) => ['value' => $event->event_id, 'label' => $event->e_name])->values(),
            'pendingPayments' => $pendingPayments,
        ]);
    }

    public function show(Event $event): Response
    {
        $event->load([
            'registrations.athlete.user:id,name,email',
            'coachRegistrations.coach.user:id,name,email',
        ]);

        return Inertia::render('ChampionshipDetailPage', [
            'isAdmin' => request()->user()?->isAdmin() ?? false,
            'isAthlete' => $request->user()?->hasRole('athlete'),
            'canManageCoaches' => request()->user()?->isAdmin() || request()->user()?->isCoach(),
            'canRecordResult' => request()->user()?->isAdmin() || request()->user()?->isCoach(),
            'event' => [
                'id' => $event->event_id,
                'name' => $event->e_name,
                'date' => Carbon::parse($event->e_date)->format('d M Y'),
                'location' => $event->location ?? '-',
                'gmaps_url' => $event->gmaps_url,
                'entry_fee' => (float) $event->entry_fee,
                'status' => $event->status,
            ],
            'athleteRows' => $event->registrations->map(fn (EventRegistration $registration) => [
                'id' => 'ATHREG-'.$registration->evrid,
                'athlete' => $registration->athlete?->user?->name ?? 'Unknown athlete',
                'category' => $registration->category,
                'division' => $registration->division ?? '-',
                'status' => $registration->status,
            ])->values(),
            'coachRows' => $event->coachRegistrations->map(fn (EventCoachRegistration $registration) => [
                'id' => 'COAREG-'.$registration->id,
                'coach' => $registration->coach?->user?->name ?? 'Unknown coach',
                'role' => $registration->role ?? '-',
            ])->values(),
            'coachOptions' => Coach::query()
                ->with('user:id,name')
                ->where('status', 'active')
                ->get()
                ->map(fn (Coach $coach) => ['value' => $coach->coach_id, 'label' => $coach->user?->name ?? 'Unknown coach'])
                ->values(),
        ]);
    }

    public function storeEvent(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'date' => ['required', 'date'],
            'location' => ['required', 'string', 'max:255'],
            'gmaps_url' => ['nullable', 'url', 'max:255'],
            'entry_fee' => ['required', 'numeric', 'min:0'],
            'max_slots' => ['nullable', 'integer', 'min:1'],
            'level' => ['nullable', Rule::in(['LOCAL', 'REGIONAL', 'NATIONAL', 'INTERNATIONAL'])],
        ]);

        Event::create([
            'e_name' => $validated['name'],
            'e_date' => $validated['date'],
            'location' => $validated['location'],
            'gmaps_url' => $validated['gmaps_url'] ?? null,
            'level' => $validated['level'] ?? 'LOCAL',
            'entry_fee' => $validated['entry_fee'],
            'max_slots' => $validated['max_slots'] ?? 24,
            'status' => 'SCHEDULED',
        ]);

        return redirect()->route('championships.index');
    }

    public function storeRegistration(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin() || $request->user()?->isParent() || $request->user()?->isAthlete(), 403);

        $validated = $request->validate([
            'athlete_id' => ['required', 'exists:athletes,athlete_id'],
            'event_id' => ['required', 'exists:events,event_id'],
            'category' => ['required', Rule::in(['KYORUGI', 'POOMSAE', 'FREESTYLE', 'UNKNOWN'])],
            'division' => ['nullable', 'string', 'max:120'],
        ]);

        $event = Event::query()
            ->withCount(['registrations' => fn ($query) => $query->whereNull('deleted_at')])
            ->findOrFail($validated['event_id']);

        $user = $request->user();
        if ($user?->isAthlete() && (int) $user->athleteProfile?->athlete_id !== (int) $validated['athlete_id']) {
            return back()->withErrors(['athlete_id' => 'Athlete can only register own account.']);
        }

        if ($user?->isParent()) {
            $childIds = $user->children()->pluck('athletes.athlete_id')->map(fn ($id) => (int) $id)->all();
            if (! in_array((int) $validated['athlete_id'], $childIds, true)) {
                return back()->withErrors(['athlete_id' => 'Parent can only register linked children.']);
            }
        }

        if ($event->registrations_count >= $event->max_slots) {
            return back()->withErrors(['event_id' => 'This championship has reached its maximum slot capacity.']);
        }

        $registration = EventRegistration::create([
            'athlete_id' => $validated['athlete_id'],
            'event_id' => $validated['event_id'],
            'category' => $validated['category'],
            'division' => $validated['division'] ?? null,
            'status' => 'PENDING',
        ]);

        Payment::query()->firstOrCreate(
            ['reference_id' => $registration->evrid],
            [
                'athlete_id' => $registration->athlete_id,
                'billable_user_id' => $registration->athlete?->id,
                'bill_kind' => 'INVOICE',
                'payment_type' => 'CHAMPIONSHIP',
                'amount' => (float) $event->entry_fee,
                'total_amount' => (float) $event->entry_fee,
                'paid_amount' => 0,
                'remaining_amount' => (float) $event->entry_fee,
                'payment_date' => now()->toDateString(),
                'status' => 'PENDING',
                'notes' => 'Event registration #'.$registration->evrid,
            ],
        );

        ActivityLogger::log(
            $request,
            'event.registration.created',
            'event',
            'Created event registration',
            $registration,
            ['event_id' => $registration->event_id, 'athlete_id' => $registration->athlete_id],
        );

        return redirect()->route('championships.index');
    }

    public function settleRegistrationPayment(Request $request, Payment $payment): RedirectResponse
    {
        $validated = $request->validate([
            'paid_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $user = $request->user();
        $athleteId = (int) $payment->athlete_id;
        if ($user?->isAthlete() && (int) $user->athleteProfile?->athlete_id !== $athleteId) {
            abort(403);
        }
        if ($user?->isParent()) {
            $childIds = $user->children()->pluck('athletes.athlete_id')->map(fn ($id) => (int) $id)->all();
            if (! in_array($athleteId, $childIds, true)) {
                abort(403);
            }
        }

        $total = (float) ($payment->total_amount ?? $payment->amount ?? 0);
        $newPaid = min((float) $payment->paid_amount + (float) $validated['paid_amount'], $total);
        $remaining = max($total - $newPaid, 0);

        $payment->update([
            'paid_amount' => $newPaid,
            'remaining_amount' => $remaining,
            'status' => $remaining <= 0 ? 'COMPLETED' : 'PENDING',
        ]);

        return redirect()->route('championships.index');
    }

    public function storeCoachRegistration(Request $request, Event $event): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin() || $request->user()?->isCoach(), 403);

        $validated = $request->validate([
            'coach_id' => ['nullable', 'exists:coaches,coach_id'],
            'role' => ['nullable', 'string', 'max:120'],
        ]);

        $coachId = $request->user()?->isAdmin()
            ? (int) ($validated['coach_id'] ?? 0)
            : (int) ($request->user()?->coachProfile?->coach_id ?? 0);

        if ($coachId <= 0) {
            return back()->withErrors(['coach_id' => 'Coach profile not found.']);
        }

        EventCoachRegistration::query()->firstOrCreate(
            ['event_id' => $event->event_id, 'coach_id' => $coachId],
            ['role' => $validated['role'] ?? null],
        );

        return redirect()->route('championships.show', $event);
    }

    public function recordResult(Request $request, EventRegistration $registration): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin() || $request->user()?->isCoach(), 403);

        $validated = $request->validate([
            'medal' => ['required', Rule::in(['GOLD', 'SILVER', 'BRONZE', 'NONE'])],
            'class_name' => ['nullable', 'string', 'max:120'],
            'division' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
        ]);

        $registration->update([
            'result_medal' => $validated['medal'],
            'result_class_name' => $validated['class_name'] ?? null,
            'result_division' => $validated['division'] ?? null,
            'result_category' => $validated['category'] ?? null,
            'status' => 'CONFIRMED',
        ]);

        $registration->loadMissing(['athlete.user', 'event']);
        $user = $registration->athlete?->user;
        if ($user) {
            UserAchievement::query()->updateOrCreate(
                ['event_registration_id' => $registration->evrid],
                [
                    'user_id' => $user->id,
                    'event_id' => $registration->event_id,
                    'championship_name' => $registration->event?->e_name ?? 'Championship',
                    'medal' => $validated['medal'],
                    'location' => $registration->event?->location,
                    'event_date' => $registration->event?->e_date,
                    'class_name' => $validated['class_name'] ?? null,
                    'division' => $validated['division'] ?? null,
                    'category' => $validated['category'] ?? null,
                    'is_auto_recorded' => true,
                ],
            );
        }

        return back();
    }

    private function eventBadge(Event $event): array
    {
        if ($event->status === 'SCHEDULED' && $event->registrations_count >= max($event->max_slots - 3, 1)) {
            return $this->badge('Closing soon', 'warning');
        }

        return match ($event->status) {
            'SCHEDULED' => $this->badge('Open', 'success'),
            'ONGOING' => $this->badge('Ongoing', 'info'),
            'COMPLETED' => $this->badge('Completed', 'neutral'),
            default => $this->badge('Canceled', 'danger'),
        };
    }

    private function eventPaymentBadge(Event $event): array
    {
        $expected = (float) ($event->entry_fee * $event->registrations_count);
        $paid = (float) \App\Models\Payment::query()
            ->where('payment_type', 'CHAMPIONSHIP')
            ->whereIn('reference_id', $event->registrations()->pluck('evrid'))
            ->sum('paid_amount');

        if ($expected <= 0) {
            return $this->badge('No bill', 'neutral');
        }

        if ($paid >= $expected) {
            return $this->badge('Full', 'success');
        }

        if ($paid > 0) {
            return $this->badge('Partial', 'warning');
        }

        return $this->badge('Unpaid', 'danger');
    }
}
