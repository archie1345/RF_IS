<?php

namespace App\Http\Controllers\Admin\Features;

use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminEventFeatureController extends BaseAdminFeatureController
{
    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $events = Event::query()->withCount('registrations')->latest('e_date')->take(100)->get();

        return $this->renderFeature('Manajemen Event / UKT', 'Event internal dan UKT. Jadwal pertandingan terpisah sudah dihapus dari navigasi.', [], ['Event', 'Tanggal & Waktu', 'Pendaftar', 'Biaya', 'Tipe & Visibilitas', 'Aksi'], 'Tidak ada data event', 'events', $events->map(fn (Event $event) => [
            'Event' => $event->e_name,
            'Tanggal & Waktu' => optional($event->e_date)->format('d M Y H:i') ?? '-',
            'Pendaftar' => (string) $event->registrations_count,
            'Biaya' => 'Rp '.number_format((float) $event->entry_fee, 0, ',', '.'),
            'Tipe & Visibilitas' => ($event->level ?? '-').' · '.($event->status ?? '-'),
            'Aksi' => route('championships.show', $event),
        ])->values()->all());
    }

    public function history(Request $request): Response
    {
        $this->authorizeAdmin($request);

        $events = Event::query()
            ->with([
                'registrations' => fn ($query) => $query
                    ->with('athlete.user:id,name')
                    ->orderBy('evrid'),
            ])
            ->withCount([
                'registrations',
                'registrations as results_count' => fn ($query) => $query->whereNotNull('result_medal'),
            ])
            ->where(function ($query): void {
                $query->whereDate('e_date', '<', now()->toDateString())
                    ->orWhere('status', 'COMPLETED');
            })
            ->latest('e_date')
            ->take(100)
            ->get();

        return Inertia::render('EventHistoryPage', [
            'events' => $events->map(fn (Event $event) => [
                'id' => $event->event_id,
                'name' => $event->e_name,
                'date' => optional($event->e_date)->format('d M Y') ?? '-',
                'date_raw' => optional($event->e_date)->format('Y-m-d') ?? '',
                'organizer' => $event->organizer ?? '-',
                'location' => $event->location ?? '-',
                'level' => $event->level ?? '-',
                'status' => $event->status ?? '-',
                'participants_count' => $event->registrations_count,
                'results_count' => $event->results_count,
                'detail_url' => route('championships.show', $event, false),
                'registrations' => $event->registrations->map(fn (EventRegistration $registration) => [
                    'id' => $registration->evrid,
                    'athlete' => $registration->athlete?->user?->name ?? 'Unknown athlete',
                    'classification' => $registration->classification ?? '-',
                    'entry_category' => $registration->category ?? '-',
                    'entry_class_name' => $registration->class_name ?? '-',
                    'entry_division' => $registration->division ?? '-',
                    'team_contingent' => $registration->team_contingent ?? '-',
                    'status' => $registration->status,
                    'result_medal' => $registration->result_medal ?? 'NONE',
                    'result_class_name' => $registration->result_class_name ?? $registration->class_name ?? '',
                    'result_division' => $registration->result_division ?? $registration->division ?? '',
                    'result_category' => $registration->result_category ?? $registration->category ?? '',
                    'has_result' => $registration->result_medal !== null,
                ])->values(),
            ])->values(),
        ]);
    }
}
