<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Payment;
use App\Services\ActiveRoleContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ChampionshipPageController extends Controller
{
    public function __construct(private readonly ActiveRoleContextService $activeRoleContext) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $role = $this->activeRoleContext->activeRole($request, $user);
        $events = Event::query()
            ->withCount('registrations')
            ->orderBy('e_date')
            ->get();
        $athleteOptions = $this->athleteOptions($request, $role);
        $registrations = $this->visibleRegistrations($request, $role, $athleteOptions);
        $currentAthleteId = $role === 'athlete' ? $user?->athleteProfile?->athlete_id : null;
        $myRegistrations = $currentAthleteId
            ? EventRegistration::query()
                ->where('athlete_id', $currentAthleteId)
                ->get()
                ->keyBy(fn (EventRegistration $registration): string => (string) $registration->event_id)
            : collect();

        return Inertia::render('ChampionshipsPage', [
            'isAdmin' => $role === 'admin',
            'isAthlete' => $role === 'athlete',
            'canRegister' => in_array($role, ['admin', 'parent', 'athlete'], true),
            'metrics' => [
                ['label' => 'Pendaftaran dibuka', 'value' => (string) $events->filter(fn (Event $event): bool => $this->registrationIsOpen($event))->count(), 'detail' => 'Kejuaraan yang masih menerima peserta', 'tone' => 'warning'],
                ['label' => 'Entri terlihat', 'value' => (string) $registrations->count(), 'detail' => 'Pendaftaran dalam konteks peran aktif', 'tone' => 'info'],
                ['label' => 'Entri terkonfirmasi', 'value' => (string) $registrations->where('status', 'CONFIRMED')->count(), 'detail' => 'Pendaftaran yang sudah dikonfirmasi', 'tone' => 'success'],
            ],
            'rows' => $events->map(function (Event $event) use ($myRegistrations, $role): array {
                /** @var EventRegistration|null $myRegistration */
                $myRegistration = $myRegistrations->get((string) $event->event_id);
                $registrationOpen = $this->registrationIsOpen($event);
                $deadline = $this->effectiveRegistrationDeadline($event);

                return [
                    'id' => 'EVT-'.$event->event_id,
                    'event_id' => $event->event_id,
                    'event' => $event->e_name,
                    'date' => optional($event->e_date)->format('d M Y') ?? '-',
                    'date_value' => optional($event->e_date)->format('Y-m-d') ?? '',
                    'registration_deadline' => $deadline->format('d M Y H:i'),
                    'registration_deadline_value' => $deadline->format('Y-m-d\TH:i'),
                    'registration_open' => $registrationOpen,
                    'location' => $event->location ?? 'TBD',
                    'gmaps_url' => $event->gmaps_url,
                    'entry_fee' => (float) $event->entry_fee,
                    'max_slots' => (int) $event->max_slots,
                    'level' => $event->level ?? 'LOCAL',
                    'status_value' => $event->status ?? 'SCHEDULED',
                    'status' => $event->status ?? 'SCHEDULED',
                    'registrations_count' => (int) $event->registrations_count,
                    'slots' => $event->registrations_count.' / '.$event->max_slots.' atlet',
                    'my_registration' => $myRegistration ? [
                        'registration_id' => $myRegistration->evrid,
                        'category' => $myRegistration->category,
                        'classification' => $myRegistration->classification ?? '',
                        'class_name' => $myRegistration->class_name ?? '',
                        'division' => $myRegistration->division ?? '',
                        'team_contingent' => $myRegistration->team_contingent ?? 'Rhino Fighter',
                    ] : null,
                    'can_edit_registration' => $role === 'athlete' && $myRegistration && $registrationOpen,
                ];
            })->values(),
            'athletes' => $athleteOptions,
            'events' => in_array($role, ['admin', 'parent', 'athlete'], true)
                ? $events
                    ->filter(fn (Event $event): bool => $this->registrationIsOpen($event))
                    ->map(fn (Event $event) => [
                        'value' => $event->event_id,
                        'label' => $event->e_name,
                    ])->values()
                : [],
            'pendingPayments' => in_array($role, ['parent', 'athlete'], true)
                ? $this->pendingChampionshipPayments($athleteOptions)
                : [],
        ]);
    }

    private function athleteOptions(Request $request, string $role): Collection
    {
        $query = Athlete::query()->with('user:id,name');
        $user = $request->user();

        if ($role === 'athlete') {
            $athleteId = $user?->athleteProfile?->athlete_id;

            if (! $athleteId) {
                return collect();
            }

            $query->where('athlete_id', $athleteId);
        } elseif ($role === 'parent') {
            $childIds = $user?->children()->pluck('athletes.athlete_id') ?? collect();
            if ($childIds->isEmpty()) {
                return collect();
            }

            $query->whereIn('athlete_id', $childIds);
        } elseif ($role !== 'admin') {
            return collect();
        }

        return $query
            ->get()
            ->map(fn (Athlete $athlete) => [
                'value' => $athlete->athlete_id,
                'label' => $athlete->user?->name ?? 'Atlet tidak dikenal',
            ])
            ->sortBy('label')
            ->values();
    }

    private function visibleRegistrations(Request $request, string $role, Collection $athleteOptions): Collection
    {
        $query = EventRegistration::query();

        if ($role === 'athlete') {
            return $query->get();
        }

        if ($role === 'parent') {
            $athleteIds = $athleteOptions->pluck('value');

            return $athleteIds->isEmpty()
                ? collect()
                : $query->whereIn('athlete_id', $athleteIds)->get();
        }

        if ($role === 'coach') {
            $coachId = $request->user()?->coachProfile?->coach_id;
            if (! $coachId) {
                return collect();
            }

            return $query
                ->whereHas('event.coachRegistrations', fn ($coaches) => $coaches->where('coach_id', $coachId))
                ->get();
        }

        return $role === 'admin' ? $query->get() : collect();
    }

    private function pendingChampionshipPayments(Collection $athleteOptions): Collection
    {
        $athleteIds = $athleteOptions->pluck('value');
        if ($athleteIds->isEmpty()) {
            return collect();
        }

        return Payment::query()
            ->where('bill_kind', 'INVOICE')
            ->where('payment_type', 'CHAMPIONSHIP')
            ->whereIn('athlete_id', $athleteIds)
            ->where('remaining_amount', '>', 0)
            ->with('athlete.user:id,name')
            ->latest('payment_date')
            ->get()
            ->map(fn (Payment $payment) => [
                'payment_id' => $payment->payment_id,
                'athlete' => $payment->athlete?->user?->name ?? 'Atlet tidak dikenal',
                'amount' => (float) ($payment->total_amount ?? $payment->amount ?? 0),
                'remaining' => (float) ($payment->remaining_amount ?? 0),
            ])
            ->values();
    }

    private function effectiveRegistrationDeadline(Event $event): Carbon
    {
        return $event->registration_deadline
            ? Carbon::parse($event->registration_deadline)
            : Carbon::parse($event->e_date)->endOfDay();
    }

    private function registrationIsOpen(Event $event): bool
    {
        return $event->status === 'SCHEDULED'
            && now(config('app.timezone', 'Asia/Jakarta'))->lt($this->effectiveRegistrationDeadline($event));
    }
}
