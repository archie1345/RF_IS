<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Coach;
use App\Models\Event;
use App\Models\Payment;
use App\Models\TrainingSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminFeatureController extends Controller
{
    public function attendance(Request $request): Response
    {
        $this->authorizeAdmin($request);

        return $this->renderFeature('Absensi Latihan', 'Kelola presensi anggota per kelas', [
            ['label' => 'Total Absensi', 'value' => (string) Attendance::count(), 'tone' => 'neutral'],
            ['label' => 'Total Hadir', 'value' => (string) Attendance::where('status', 'PRESENT')->count(), 'tone' => 'success'],
            ['label' => 'Total Izin', 'value' => (string) Attendance::where('status', 'EXCUSED')->count(), 'tone' => 'info'],
            ['label' => 'Total Sakit', 'value' => '0', 'tone' => 'warning'],
        ], ['No', 'Tanggal', 'Member / Pelatih', 'Kelas', 'Check-In', 'Check-Out', 'Status', 'Aksi'], 'Tidak ada data absensi', 'attendance');
    }

    public function instructorAttendance(Request $request): Response
    {
        $this->authorizeAdmin($request);

        return $this->renderFeature('Presensi Pelatih', 'Rekapitulasi kehadiran untuk pembayaran honor', [], ['No', 'ID', 'Tanggal', 'Pelatih', 'Kelas', 'Jam', 'Status', 'Aksi'], 'Tidak ada data presensi ditemukan', 'instructor-attendance');
    }

    public function payments(Request $request): Response
    {
        $this->authorizeAdmin($request);

        return $this->renderFeature('Validasi Keuangan', 'Verifikasi bukti pembayaran yang masuk.', [], ['ID & Tanggal', 'Member', 'Tipe & Keterangan', 'Nominal', 'Metode', 'Aksi'], 'Tidak ada data pembayaran pending.', 'payments');
    }

    public function monthlyDues(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $total = Athlete::count();
        $paid = Payment::whereMonth('payment_date', now()->month)->whereYear('payment_date', now()->year)->where('remaining_amount', '<=', 0)->count();

        return $this->renderFeature('Input Iuran', 'Input dan pantau iuran bulanan member langsung dari halaman ini.', [
            ['label' => 'Total Member', 'value' => (string) $total, 'tone' => 'info'],
            ['label' => 'Sudah Bayar', 'value' => (string) $paid, 'tone' => 'success'],
            ['label' => 'Belum Bayar', 'value' => (string) max($total - $paid, 0), 'tone' => 'danger'],
            ['label' => 'Total Masuk', 'value' => 'Rp '.number_format((float) Payment::whereMonth('payment_date', now()->month)->sum('paid_amount'), 0, ',', '.'), 'tone' => 'info'],
        ], ['Nama Member', 'No. HP', 'Tipe Iuran', 'Nominal', 'Tanggal Bayar', 'No. Transaksi', 'Aksi'], 'Belum ada data iuran bulan ini.', 'monthly-dues');
    }

    public function members(Request $request): Response
    {
        $this->authorizeAdmin($request);

        return $this->renderFeature('Manajemen Anggota', 'DOJANG: RTFCM · Total '.Athlete::count().' anggota terdaftar', [], ['No.', 'Anggota', 'Pribadi', 'Sabuk', 'Kontak', 'Dokumen', 'Status', 'Aksi'], 'Tidak ada data anggota', 'members');
    }

    public function instructors(Request $request): Response
    {
        $this->authorizeAdmin($request);

        return $this->renderFeature('Master Pelatih', 'DOJANG: RTFCM · Total '.Coach::count().' pelatih terdaftar', [], ['No.', 'Pelatih', 'Spesialisasi', 'Sabuk', 'Sertifikat', 'Kontak', 'Status', 'Aksi'], 'Data tidak ditemukan', 'instructors');
    }

    public function events(Request $request): Response
    {
        $this->authorizeAdmin($request);

        return $this->renderFeature('Manajemen Event', 'DOJANG: RTFCM · Total '.Event::count().' event terdaftar', [], ['Event', 'Tanggal & Waktu', 'Batas Pendaftaran', 'Pendaftar', 'Biaya', 'Tipe & Visibilitas', 'Aksi'], 'Tidak ada data event', 'events');
    }

    public function eventHistory(Request $request): Response
    {
        $this->authorizeAdmin($request);

        return $this->renderFeature('Riwayat Event & UKT', 'Menampilkan event yang pernah diikuti/diselenggarakan', [], ['Event', 'Tanggal', 'Penyelenggara', 'Peserta Anda', 'Total Peserta', 'Tipe', 'Aksi'], 'Tidak ada riwayat event', 'event-history');
    }

    public function eventSchedule(Request $request): Response
    {
        $this->authorizeAdmin($request);

        return $this->renderFeature('Jadwal Pertandingan', 'Publikasi & gate display untuk jadwal pertandingan.', [], ['Mat', 'Waktu', 'Kontingen', 'Partai', 'Status', 'Aksi'], 'Pilih event terlebih dahulu untuk mengaktifkan launcher jadwal.', 'event-schedule');
    }

    public function locations(Request $request): Response
    {
        $this->authorizeAdmin($request);

        return $this->renderFeature('Manajemen Lokasi', 'DOJANG: RTFCM · Total lokasi terdaftar', [], ['Nama Lokasi & Kelas', 'Alamat', 'Status', 'Aksi'], 'Tidak ada data lokasi', 'locations');
    }

    public function classes(Request $request): Response
    {
        $this->authorizeAdmin($request);

        return $this->renderFeature('Manajemen Kelas', 'DOJANG: RTFCM · Total '.TrainingSession::where('status', '!=', 'CANCELED')->count().' jadwal kelas aktif', [], ['Kelas', 'Instruktur', 'Jadwal', 'Kapasitas', 'Status', 'Aksi'], 'Tidak ada data kelas', 'classes');
    }

    public function weeklySchedules(Request $request): Response
    {
        $this->authorizeAdmin($request);

        return Inertia::render('AdminFeaturePage', [
            'mode' => 'weekly-schedule',
            'title' => 'Jadwal Mingguan',
            'subtitle' => 'Jadwal latihan rutin RTFCM',
            'metrics' => [],
            'columns' => [],
            'rows' => [],
            'emptyText' => '',
            'roleAccess' => 'Admin only',
            'todaySessions' => TrainingSession::query()->whereDate('session_date', now()->toDateString())->where('status', '!=', 'CANCELED')->get()->map(fn (TrainingSession $session) => [
                'title' => $session->title,
                'time' => substr((string) $session->start_time, 0, 5).' - '.substr((string) $session->end_time, 0, 5),
                'location' => $session->location ?? 'RTFCM',
            ])->values(),
        ]);
    }

    public function dailySchedules(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $sessionsToday = TrainingSession::whereDate('session_date', now()->toDateString())->where('status', '!=', 'CANCELED')->count();

        return $this->renderFeature('Jadwal Latihan Harian', 'Pantau pelaksanaan kelas dan kehadiran anggota di seluruh unit hari ini.', [
            ['label' => 'Total Kelas', 'value' => (string) $sessionsToday, 'tone' => 'info'],
            ['label' => 'Kelas Berjalan', 'value' => '0', 'tone' => 'success'],
            ['label' => 'Siswa Terlibat', 'value' => (string) Attendance::whereDate('date', now()->toDateString())->count(), 'tone' => 'warning'],
            ['label' => 'Pelatih Hadir', 'value' => '0', 'tone' => 'danger'],
        ], ['Waktu Sesi', 'Nama Kelas & Tipe', 'Pelatih (Check-in)', 'Siswa Hadir', 'Status'], 'Tidak ada sesi hari ini.', 'daily-schedules');
    }

    public function periodicStats(Request $request): Response
    {
        $this->authorizeAdmin($request);

        return $this->renderFeature('Dashboard Statistik Per Periode', 'Analisis laju perkembangan Member, Pelatih, dan Dojang secara bulanan.', [
            ['label' => 'Pertumbuhan Anggota', 'value' => '+0 Member Baru', 'tone' => 'info'],
            ['label' => 'Pertumbuhan Pelatih', 'value' => '+0 Pelatih Baru', 'tone' => 'warning'],
        ], ['Tanggal', 'Member Baru', 'Pelatih Baru', 'Total'], 'Belum ada pertumbuhan pada periode ini.', 'periodic-stats');
    }

    private function renderFeature(string $title, string $subtitle, array $metrics, array $columns, string $emptyText, string $mode): Response
    {
        return Inertia::render('AdminFeaturePage', [
            'mode' => $mode,
            'title' => $title,
            'subtitle' => $subtitle,
            'metrics' => $metrics,
            'columns' => $columns,
            'rows' => [],
            'emptyText' => $emptyText,
            'roleAccess' => 'Admin only',
            'todaySessions' => [],
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }
}
