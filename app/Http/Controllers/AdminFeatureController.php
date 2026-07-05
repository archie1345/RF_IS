<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Coach;
use App\Models\Event;
use App\Models\Payment;
use App\Models\TrainingSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

    public function financeIncome(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $payments = Payment::query()->where('bill_kind', '!=', 'PAYROLL')->get();

        return $this->renderFeature('Keuangan Masuk', 'Pantau tagihan, iuran, dan bukti pembayaran anggota.', [
            ['label' => 'Total Tagihan', 'value' => 'Rp '.number_format((float) $payments->sum('total_amount'), 0, ',', '.'), 'tone' => 'info'],
            ['label' => 'Sudah Masuk', 'value' => 'Rp '.number_format((float) $payments->sum('paid_amount'), 0, ',', '.'), 'tone' => 'success'],
            ['label' => 'Belum Dibayar', 'value' => 'Rp '.number_format((float) $payments->sum('remaining_amount'), 0, ',', '.'), 'tone' => 'danger'],
            ['label' => 'Butuh Review', 'value' => (string) $payments->where('proof_status', 'SUBMITTED')->count(), 'tone' => 'warning'],
        ], ['Tanggal', 'Member', 'Tipe', 'Total', 'Dibayar', 'Sisa', 'Status'], 'Belum ada transaksi masuk.', 'finance-income', $payments->take(50)->map(fn (Payment $payment) => [
            'Tanggal' => optional($payment->payment_date)->format('d M Y') ?? '-',
            'Member' => $payment->athlete?->user?->name ?? $payment->billableUser?->name ?? '-',
            'Tipe' => $payment->payment_type,
            'Total' => 'Rp '.number_format((float) ($payment->total_amount ?? $payment->amount), 0, ',', '.'),
            'Dibayar' => 'Rp '.number_format((float) $payment->paid_amount, 0, ',', '.'),
            'Sisa' => 'Rp '.number_format((float) $payment->remaining_amount, 0, ',', '.'),
            'Status' => $payment->status,
        ])->values()->all());
    }

    public function financeOutput(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $payments = Payment::query()->where('bill_kind', 'PAYROLL')->get();

        return $this->renderFeature('Keuangan Keluar', 'Catat dan lacak honor pelatih serta pengeluaran operasional.', [
            ['label' => 'Total Payout', 'value' => 'Rp '.number_format((float) $payments->sum('total_amount'), 0, ',', '.'), 'tone' => 'warning'],
            ['label' => 'Sudah Dibayar', 'value' => 'Rp '.number_format((float) $payments->sum('paid_amount'), 0, ',', '.'), 'tone' => 'success'],
            ['label' => 'Belum Dibayar', 'value' => 'Rp '.number_format((float) $payments->sum('remaining_amount'), 0, ',', '.'), 'tone' => 'danger'],
            ['label' => 'Coach Payout', 'value' => (string) $payments->count(), 'tone' => 'info'],
        ], ['Tanggal', 'Penerima', 'Tipe', 'Total', 'Dibayar', 'Sisa', 'Status'], 'Belum ada transaksi keluar.', 'finance-output', $payments->take(50)->map(fn (Payment $payment) => [
            'Tanggal' => optional($payment->payment_date)->format('d M Y') ?? '-',
            'Penerima' => $payment->payeeUser?->name ?? '-',
            'Tipe' => 'Coach salary / payout',
            'Total' => 'Rp '.number_format((float) ($payment->total_amount ?? $payment->amount), 0, ',', '.'),
            'Dibayar' => 'Rp '.number_format((float) $payment->paid_amount, 0, ',', '.'),
            'Sisa' => 'Rp '.number_format((float) $payment->remaining_amount, 0, ',', '.'),
            'Status' => $payment->status,
        ])->values()->all());
    }

    public function monthlyDues(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $total = Athlete::count();
        $paid = Payment::whereMonth('payment_date', now()->month)->whereYear('payment_date', now()->year)->where('payment_type', 'TUITION')->where('remaining_amount', '<=', 0)->count();

        return $this->renderFeature('Input Iuran', 'Input dan pantau iuran bulanan member langsung dari halaman ini. Tagihan otomatis dibuat setiap tanggal 1.', [
            ['label' => 'Total Member', 'value' => (string) $total, 'tone' => 'info'],
            ['label' => 'Sudah Bayar', 'value' => (string) $paid, 'tone' => 'success'],
            ['label' => 'Belum Bayar', 'value' => (string) max($total - $paid, 0), 'tone' => 'danger'],
            ['label' => 'Total Masuk', 'value' => 'Rp '.number_format((float) Payment::whereMonth('payment_date', now()->month)->where('payment_type', 'TUITION')->sum('paid_amount'), 0, ',', '.'), 'tone' => 'info'],
        ], ['Nama Member', 'No. HP', 'Tipe Iuran', 'Nominal', 'Tanggal Bayar', 'No. Transaksi', 'Aksi'], 'Belum ada data iuran bulan ini.', 'monthly-dues');
    }

    public function generateMonthlyDues(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        \Artisan::call('tuition:generate-monthly');

        return back()->with('status', trim(\Artisan::output()));
    }

    public function members(Request $request): Response { $this->authorizeAdmin($request); return $this->renderFeature('Manajemen Anggota', 'DOJANG: RTFCM · Total '.Athlete::count().' anggota terdaftar', [], ['No.', 'Anggota', 'Pribadi', 'Sabuk', 'Kontak', 'Dokumen', 'Status', 'Aksi'], 'Tidak ada data anggota', 'members'); }
    public function instructors(Request $request): Response { $this->authorizeAdmin($request); return $this->renderFeature('Master Pelatih', 'DOJANG: RTFCM · Total '.Coach::count().' pelatih terdaftar', [], ['No.', 'Pelatih', 'Spesialisasi', 'Sabuk', 'Sertifikat', 'Kontak', 'Status', 'Aksi'], 'Data tidak ditemukan', 'instructors'); }
    public function events(Request $request): Response { $this->authorizeAdmin($request); return $this->renderFeature('Manajemen Event', 'DOJANG: RTFCM · Total '.Event::count().' event terdaftar', [], ['Event', 'Tanggal & Waktu', 'Batas Pendaftaran', 'Pendaftar', 'Biaya', 'Tipe & Visibilitas', 'Aksi'], 'Tidak ada data event', 'events'); }
    public function eventHistory(Request $request): Response { $this->authorizeAdmin($request); return $this->renderFeature('Riwayat Event & UKT', 'Menampilkan event yang pernah diikuti/diselenggarakan', [], ['Event', 'Tanggal', 'Penyelenggara', 'Peserta Anda', 'Total Peserta', 'Tipe', 'Aksi'], 'Tidak ada riwayat event', 'event-history'); }
    public function eventSchedule(Request $request): Response { $this->authorizeAdmin($request); return $this->renderFeature('Jadwal Pertandingan', 'Publikasi & gate display untuk jadwal pertandingan.', [], ['Mat', 'Waktu', 'Kontingen', 'Partai', 'Status', 'Aksi'], 'Pilih event terlebih dahulu untuk mengaktifkan launcher jadwal.', 'event-schedule'); }
    public function locations(Request $request): Response { $this->authorizeAdmin($request); return $this->renderFeature('Manajemen Lokasi', 'DOJANG: RTFCM · Total lokasi terdaftar', [], ['Nama Lokasi & Kelas', 'Alamat', 'Status', 'Aksi'], 'Tidak ada data lokasi', 'locations'); }
    public function classes(Request $request): Response { $this->authorizeAdmin($request); return $this->renderFeature('Manajemen Kelas', 'DOJANG: RTFCM · Total '.TrainingSession::where('status', '!=', 'CANCELED')->count().' jadwal kelas aktif', [], ['Kelas', 'Instruktur', 'Jadwal', 'Kapasitas', 'Status', 'Aksi'], 'Tidak ada data kelas', 'classes'); }

    public function weeklySchedules(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $sessions = TrainingSession::query()->whereBetween('session_date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])->where('status', '!=', 'CANCELED')->get();

        return Inertia::render('AdminFeaturePage', [
            'mode' => 'weekly-schedule',
            'title' => 'Jadwal Mingguan',
            'subtitle' => 'Jadwal latihan rutin RTFCM. Halaman ini menjadi preview mingguan; tombol generate membuat sesi dari jadwal aktif yang sudah ada.',
            'metrics' => [
                ['label' => 'Sesi Minggu Ini', 'value' => (string) $sessions->count(), 'tone' => 'info'],
                ['label' => 'Hari Latihan', 'value' => (string) $sessions->pluck('session_date')->unique()->count(), 'tone' => 'success'],
                ['label' => 'Sesi Hari Ini', 'value' => (string) $sessions->where('session_date', now()->toDateString())->count(), 'tone' => 'warning'],
            ],
            'columns' => [], 'rows' => [], 'emptyText' => '', 'roleAccess' => 'Admin only',
            'todaySessions' => $sessions->map(fn (TrainingSession $session) => ['title' => $session->title, 'time' => substr((string) $session->start_time, 0, 5).' - '.substr((string) $session->end_time, 0, 5), 'location' => $session->location ?? 'RTFCM', 'date' => Carbon::parse((string) $session->session_date)->format('Y-m-d')])->values(),
        ]);
    }

    public function generateWeeklySessions(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $created = 0;
        $templates = TrainingSession::query()->whereBetween('session_date', [now()->subWeeks(2)->toDateString(), now()->subDay()->toDateString()])->where('status', '!=', 'CANCELED')->get()->groupBy(fn (TrainingSession $s) => Carbon::parse((string) $s->session_date)->dayOfWeek);
        foreach ($templates as $dayOfWeek => $items) {
            $date = now()->startOfWeek()->addDays(((int) $dayOfWeek + 6) % 7)->toDateString();
            foreach ($items->take(3) as $template) {
                $exists = TrainingSession::query()->whereDate('session_date', $date)->where('start_time', $template->start_time)->where('group_id', $template->group_id)->exists();
                if ($exists) continue;
                $copy = $template->replicate(['attendance_token_hash', 'attendance_opens_at', 'attendance_closes_at', 'attendance_qr_generated_at', 'attendance_qr_revoked_at']);
                $copy->session_date = $date;
                $copy->status = 'CONFIRMED';
                $copy->save();
                $created++;
            }
        }

        return back()->with('status', "Generated {$created} weekly sessions.");
    }

    public function dailySchedules(Request $request): Response { $this->authorizeAdmin($request); $sessionsToday = TrainingSession::whereDate('session_date', now()->toDateString())->where('status', '!=', 'CANCELED')->count(); return $this->renderFeature('Jadwal Latihan Harian', 'Pantau pelaksanaan kelas dan kehadiran anggota di seluruh unit hari ini.', [['label' => 'Total Kelas', 'value' => (string) $sessionsToday, 'tone' => 'info'], ['label' => 'Kelas Berjalan', 'value' => '0', 'tone' => 'success'], ['label' => 'Siswa Terlibat', 'value' => (string) Attendance::whereDate('date', now()->toDateString())->count(), 'tone' => 'warning'], ['label' => 'Pelatih Hadir', 'value' => '0', 'tone' => 'danger']], ['Waktu Sesi', 'Nama Kelas & Tipe', 'Pelatih (Check-in)', 'Siswa Hadir', 'Status'], 'Tidak ada sesi hari ini.', 'daily-schedules'); }
    public function periodicStats(Request $request): Response { $this->authorizeAdmin($request); return $this->renderFeature('Dashboard Statistik Per Periode', 'Analisis laju perkembangan Member, Pelatih, dan Dojang secara bulanan.', [['label' => 'Pertumbuhan Anggota', 'value' => '+0 Member Baru', 'tone' => 'info'], ['label' => 'Pertumbuhan Pelatih', 'value' => '+0 Pelatih Baru', 'tone' => 'warning']], ['Tanggal', 'Member Baru', 'Pelatih Baru', 'Total'], 'Belum ada pertumbuhan pada periode ini.', 'periodic-stats'); }

    private function renderFeature(string $title, string $subtitle, array $metrics, array $columns, string $emptyText, string $mode, array $rows = []): Response
    {
        return Inertia::render('AdminFeaturePage', ['mode' => $mode, 'title' => $title, 'subtitle' => $subtitle, 'metrics' => $metrics, 'columns' => $columns, 'rows' => $rows, 'emptyText' => $emptyText, 'roleAccess' => 'Admin only', 'todaySessions' => []]);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }
}
