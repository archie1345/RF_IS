<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ChampionshipPageController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $role = $user?->primaryRole() ?? 'athlete';
        $events = Event::query()
            ->withCount(['registrations' => fn ($query) => $query->whereNull('deleted_at')])
            ->orderBy('e_date')
            ->get();
        $athleteOptions = $this->athleteOptions($request, $role);
        $registrations = $this->visibleRegistrations($request, $role, $athleteOptions);

        return Inertia::render('ChampionshipsPage', [
            'isAdmin' => $role === 'admin',
            'isAthlete' => $role === 'athlete',
            'canRegister' => in_array($role, ['admin', 'parent', 'athlete'], true),
            'metrics' => [
                ['label' => 'Pendaftaran dibuka', 'value' => (string) $events->where('status', 'SCHEDULED')->count(), 'detail' => 'Kejuaraan yang masih menerima peserta', 'tone' => 'warning'],
                ['label' => 'Entri terlihat', 'value' => (string) $registrations->count(), 'detail' => 'Pendaftaran dalam konteks peran aktif', 'tone' => 'info'],
                ['label' => 'Entri terkonfirmasi', 'value' => (string) $registrations->where('status', 'CONFIRMED')->count(), 'detail' => 'Pendaftaran yang sudah dikonfirmasi', 'tone' => 'success'],
            ],
            'rows' => $events->map(fn (Event $event) => [
                'id' => 'EVT-'.$event->event_id,
                'event_id' => $event->event_id,
                'event' => $event->e_name,
                'date' => Carbon::parse($event->e_date)->format('d M Y'),
                'date_value' => Carbon::parse($event->e_date)->format('Y-m-d'),
                'location' => $event->location ?? 'TBD',
                'gmaps_url' => $event->gmaps_url,
                'entry_fee' => (float) $event->entry_fee,
                'max_slots' => (int) $event->max_slots,
                'level' => $event->level ?? 'LOCAL',
                'status_value' => $event->status ?? 'SCHEDULED',
                'status' => $event->status ?? 'SCHEDULED',
                'registrations_count' => (int) $event->registrations_count,
                'slots' => $event->registrations_count.' / '.$event->max_slots.' atlet',
            ])->values(),
            'athletes' => $athleteOptions,
            'events' => in_array($role, ['admin', 'parent', 'athlete'], true)
                ? $events->where('status', 'SCHEDULED')->map(fn (Event $event) => [
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
            $query->where('athlete_id', $user?->athleteProfile?->athlete_id);
        } elseif ($role === 'parent') {
            $query->whereIn('athlete_id', $user->children()->pluck('athletes.athlete_id'));
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

        if (in_array($role, ['athlete', 'parent'], true)) {
            $query->whereIn('athlete_id', $athleteOptions->pluck('value'));
        } elseif (! in_array($role, ['admin', 'coach'], true)) {
            return collect();
        }

        return $query->get();
    }

    private function pendingChampionshipPayments(Collection $athleteOptions): Collection
    {
        return Payment::query()
            ->where('bill_kind', 'INVOICE')
            ->where('payment_type', 'CHAMPIONSHIP')
            ->whereIn('athlete_id', $athleteOptions->pluck('value'))
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
}
