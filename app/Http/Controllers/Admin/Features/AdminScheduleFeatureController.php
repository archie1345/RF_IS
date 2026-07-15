<?php

namespace App\Http\Controllers\Admin\Features;

use App\Models\TrainingSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminScheduleFeatureController extends BaseAdminFeatureController
{
    public function daily(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $date = $request->date('date')?->toDateString() ?? now()->toDateString();
        $sessions = TrainingSession::query()
            ->with(['group', 'branch', 'primaryCoach.user'])
            ->withCount(['attendances as present_count' => fn ($query) => $query->where('status', 'PRESENT')])
            ->whereDate('session_date', $date)
            ->where('status', '!=', 'CANCELED')
            ->orderBy('start_time')
            ->get();

        return $this->renderFeature('Jadwal Latihan Harian', 'Pantau pelaksanaan kelas hari ini.', [
            ['label' => 'Total Kelas', 'value' => (string) $sessions->count(), 'tone' => 'info'],
            ['label' => 'Siswa Hadir', 'value' => (string) $sessions->sum('present_count'), 'tone' => 'warning'],
        ], ['Waktu Sesi', 'Nama Kelas', 'Pelatih', 'Siswa Hadir', 'Status'], 'Tidak ada sesi hari ini.', 'daily-schedules', $sessions->map(fn (TrainingSession $session) => [
            'Waktu Sesi' => substr((string) $session->start_time, 0, 5).' - '.substr((string) $session->end_time, 0, 5),
            'Nama Kelas' => $session->group?->group_name ?? $session->title,
            'Pelatih' => $session->primaryCoach?->user?->name ?? '-',
            'Siswa Hadir' => (string) $session->present_count,
            'Status' => $session->status,
        ])->values()->all());
    }

    public function disabledReports(Request $request): Response
    {
        $this->authorizeAdmin($request);

        return Inertia::render('AdminFeaturePage', [
            'mode' => 'periodic-stats',
            'title' => 'Laporan dinonaktifkan',
            'subtitle' => 'Halaman laporan sudah dihapus dari navigasi.',
            'metrics' => [],
            'columns' => [],
            'rows' => [],
            'emptyText' => '',
        ]);
    }
}
