<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FormatsMvpData;
use App\Models\Athlete;
use App\Models\Event;
use App\Models\EventRegistration;
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
        $events = Event::query()
            ->withCount(['registrations' => fn ($query) => $query->whereNull('deleted_at')])
            ->orderBy('e_date')
            ->get();

        $registrations = EventRegistration::query()->get();

        return Inertia::render('ChampionshipsPage', [
            'metrics' => [
                ['label' => 'Open registrations', 'value' => (string) $events->where('status', 'SCHEDULED')->count(), 'detail' => 'Published events currently taking entries', 'tone' => 'warning'],
                ['label' => 'Athletes submitted', 'value' => (string) $registrations->count(), 'detail' => 'Total registration records created', 'tone' => 'info'],
                ['label' => 'Confirmed entries', 'value' => (string) $registrations->where('status', 'CONFIRMED')->count(), 'detail' => 'Approved registrations ready for travel', 'tone' => 'success'],
            ],
            'rows' => $events->map(fn (Event $event) => [
                'id' => 'EVT-'.$event->event_id,
                'event' => $event->e_name,
                'date' => Carbon::parse($event->e_date)->format('d M Y'),
                'location' => $event->location ?? 'TBD',
                'registration' => $this->eventBadge($event),
                'payment' => $this->eventPaymentBadge($event),
                'slots' => $event->registrations_count.' / '.$event->max_slots.' athletes',
            ])->values(),
            'athletes' => Athlete::query()
                ->with('user:id,name')
                ->get()
                ->map(fn (Athlete $athlete) => ['value' => $athlete->athlete_id, 'label' => $athlete->user?->name ?? 'Unknown athlete'])
                ->sortBy('label')
                ->values(),
            'events' => $events->map(fn (Event $event) => ['value' => $event->event_id, 'label' => $event->e_name])->values(),
        ]);
    }

    public function storeRegistration(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'athlete_id' => ['required', 'exists:athletes,athlete_id'],
            'event_id' => ['required', 'exists:events,event_id'],
            'category' => ['required', Rule::in(['KYORUGI', 'POOMSAE', 'FREESTYLE', 'UNKNOWN'])],
            'division' => ['nullable', 'string', 'max:120'],
        ]);

        $event = Event::query()
            ->withCount(['registrations' => fn ($query) => $query->whereNull('deleted_at')])
            ->findOrFail($validated['event_id']);

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
            ->where('reference_id', (string) $event->event_id)
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

