<?php

namespace App\Http\Controllers\Admin\Features;

use App\Models\Event;
use Illuminate\Http\Request;
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
            'Aksi' => 'Detail',
        ])->values()->all());
    }

    public function history(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $events = Event::query()
            ->withCount('registrations')
            ->whereDate('e_date', '<', now()->toDateString())
            ->latest('e_date')
            ->take(100)
            ->get();

        return $this->renderFeature('Riwayat Event & UKT', 'Event yang pernah diselenggarakan.', [], ['Event', 'Tanggal', 'Penyelenggara', 'Total Peserta', 'Tipe', 'Aksi'], 'Tidak ada riwayat event', 'event-history', $events->map(fn (Event $event) => [
            'Event' => $event->e_name,
            'Tanggal' => optional($event->e_date)->format('d M Y') ?? '-',
            'Penyelenggara' => $event->organizer ?? '-',
            'Total Peserta' => (string) $event->registrations_count,
            'Tipe' => $event->level ?? '-',
            'Aksi' => 'Detail',
        ])->values()->all());
    }
}
