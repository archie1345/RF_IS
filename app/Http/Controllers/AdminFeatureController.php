<?php

namespace App\Http\Controllers;

use App\Actions\Sessions\GenerateWeeklyTrainingSessions;
use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\BillingSetting;
use App\Models\Branch;
use App\Models\Coach;
use App\Models\Event;
use App\Models\Group;
use App\Models\Payment;
use App\Models\TrainingSession;
use App\Models\WeeklyTrainingSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;

class AdminFeatureController extends Controller
{
    public function attendance(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $from = $request->date('from')?->startOfDay() ?? now()->startOfMonth();
        $to = $request->date('to')?->endOfDay() ?? now()->endOfDay();
        $attendances = Attendance::query()->with(['athlete.user', 'trainingSession.group'])->whereBetween('date', [$from->toDateString(), $to->toDateString()])->latest('date')->latest('checked_in_at')->take(100)->get();

        return $this->renderFeature('Absensi Latihan', 'Kelola presensi anggota per kelas', [
            ['label' => 'Total Absensi', 'value' => (string) $attendances->count(), 'tone' => 'neutral'],
            ['label' => 'Total Hadir', 'value' => (string) $attendances->where('status', 'PRESENT')->count(), 'tone' => 'success'],
            ['label' => 'Total Izin', 'value' => (string) $attendances->where('status', 'EXCUSED')->count(), 'tone' => 'info'],
            ['label' => 'Total Sakit', 'value' => (string) $attendances->where('status', 'SICK')->count(), 'tone' => 'warning'],
        ], ['No', 'Tanggal', 'Member / Pelatih', 'Kelas', 'Check-In', 'Status'], 'Tidak ada data absensi', 'attendance', $attendances->values()->map(fn (Attendance $attendance, int $index) => [
            'No' => (string) ($index + 1),
            'Tanggal' => optional($attendance->date)->format('d M Y') ?? '-',
            'Member / Pelatih' => $attendance->athlete?->user?->name ?? $attendance->athlete_id,
            'Kelas' => $attendance->trainingSession?->group?->group_name ?? $attendance->trainingSession?->title ?? '-',
            'Check-In' => optional($attendance->checked_in_at)->format('H:i') ?? '-',
            'Status' => $attendance->status,
        ])->all());
    }

    public function instructorAttendance(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $sessions = TrainingSession::query()->with(['primaryCoach.user', 'group'])->whereNotNull('coach_id')->whereMonth('session_date', $request->integer('month', now()->month))->whereYear('session_date', $request->integer('year', now()->year))->latest('session_date')->take(100)->get();

        return $this->renderFeature('Presensi Pelatih', 'Rekapitulasi kehadiran untuk pembayaran honor', [
            ['label' => 'Total Jadwal', 'value' => (string) $sessions->count(), 'tone' => 'info'],
            ['label' => 'Pelatih Terjadwal', 'value' => (string) $sessions->pluck('coach_id')->filter()->unique()->count(), 'tone' => 'success'],
        ], ['No', 'ID', 'Tanggal', 'Pelatih', 'Kelas', 'Jam', 'Status'], 'Tidak ada data presensi ditemukan', 'instructor-attendance', $sessions->values()->map(fn (TrainingSession $session, int $index) => [
            'No' => (string) ($index + 1),
            'ID' => (string) $session->training_session_id,
            'Tanggal' => optional($session->session_date)->format('d M Y') ?? '-',
            'Pelatih' => $session->primaryCoach?->user?->name ?? '-',
            'Kelas' => $session->group?->group_name ?? $session->title,
            'Jam' => substr((string) $session->start_time, 0, 5).' - '.substr((string) $session->end_time, 0, 5),
            'Status' => $session->status,
        ])->all());
    }

    public function payments(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $payments = Payment::query()->with(['athlete.user', 'billableUser'])->latest('payment_date')->latest('payment_id')->take(100)->get();

        return $this->renderFeature('Validasi Bukti Pembayaran', 'Upload, review, approve, reject, dan riwayat bukti pembayaran.', [
            ['label' => 'Perlu Verifikasi', 'value' => (string) $payments->whereIn('proof_status', ['PENDING', 'SUBMITTED'])->count(), 'tone' => 'warning'],
            ['label' => 'Data Lunas', 'value' => (string) $payments->where('remaining_amount', '<=', 0)->count(), 'tone' => 'success'],
            ['label' => 'Ditolak/Gagal', 'value' => (string) $payments->whereIn('status', ['FAILED', 'REJECTED'])->count(), 'tone' => 'danger'],
            ['label' => 'Total Masuk', 'value' => 'Rp '.number_format((float) $payments->sum('paid_amount'), 0, ',', '.'), 'tone' => 'info'],
        ], ['ID & Tanggal', 'Member', 'Tipe & Keterangan', 'Nominal', 'Metode', 'Aksi'], 'Tidak ada bukti pembayaran pending.', 'payments', $payments->map(fn (Payment $payment) => [
            'ID & Tanggal' => '#'.$payment->payment_id.' · '.(optional($payment->payment_date)->format('d M Y') ?? '-'),
            'Member' => $payment->athlete?->user?->name ?? $payment->billableUser?->name ?? '-',
            'Tipe & Keterangan' => trim($payment->payment_type.' '.$payment->notes),
            'Nominal' => 'Rp '.number_format((float) ($payment->total_amount ?? $payment->amount), 0, ',', '.'),
            'Metode' => $payment->reference_id ?? '-',
            'Aksi' => $payment->proof_path ? 'Lihat bukti' : '-',
        ])->values()->all());
    }

    public function financeIncome(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $payments = Payment::query()->where('bill_kind', '!=', 'PAYROLL')->with(['athlete.user', 'billableUser'])->latest('payment_date')->get();

        return $this->renderFeature('Keuangan Masuk', 'Ledger uang masuk dari tagihan anggota.', [
            ['label' => 'Total Tagihan', 'value' => 'Rp '.number_format((float) $payments->sum('total_amount'), 0, ',', '.'), 'tone' => 'info'],
            ['label' => 'Uang Masuk', 'value' => 'Rp '.number_format((float) $payments->sum('paid_amount'), 0, ',', '.'), 'tone' => 'success'],
            ['label' => 'Piutang', 'value' => 'Rp '.number_format((float) $payments->sum('remaining_amount'), 0, ',', '.'), 'tone' => 'danger'],
            ['label' => 'Invoice Aktif', 'value' => (string) $payments->count(), 'tone' => 'warning'],
        ], ['Tanggal', 'Sumber', 'Kategori', 'Total', 'Masuk', 'Sisa', 'Status'], 'Belum ada transaksi masuk.', 'finance-income', $payments->take(50)->map(fn (Payment $payment) => [
            'Tanggal' => optional($payment->payment_date)->format('d M Y') ?? '-',
            'Sumber' => $payment->athlete?->user?->name ?? $payment->billableUser?->name ?? '-',
            'Kategori' => $payment->payment_type,
            'Total' => 'Rp '.number_format((float) ($payment->total_amount ?? $payment->amount), 0, ',', '.'),
            'Masuk' => 'Rp '.number_format((float) $payment->paid_amount, 0, ',', '.'),
            'Sisa' => 'Rp '.number_format((float) $payment->remaining_amount, 0, ',', '.'),
            'Status' => $payment->status,
        ])->values()->all());
    }

    public function financeOutput(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $payments = Payment::query()->where('bill_kind', 'PAYROLL')->with('payeeUser')->latest('payment_date')->get();

        return $this->renderFeature('Keuangan Keluar', 'Ledger uang keluar untuk honor pelatih dan operasional.', [
            ['label' => 'Total Pengeluaran', 'value' => 'Rp '.number_format((float) $payments->sum('total_amount'), 0, ',', '.'), 'tone' => 'warning'],
            ['label' => 'Sudah Dibayar', 'value' => 'Rp '.number_format((float) $payments->sum('paid_amount'), 0, ',', '.'), 'tone' => 'success'],
            ['label' => 'Belum Dibayar', 'value' => 'Rp '.number_format((float) $payments->sum('remaining_amount'), 0, ',', '.'), 'tone' => 'danger'],
            ['label' => 'Payout Coach', 'value' => (string) $payments->count(), 'tone' => 'info'],
        ], ['Tanggal', 'Penerima', 'Kategori', 'Total', 'Dibayar', 'Sisa', 'Status'], 'Belum ada transaksi keluar.', 'finance-output', $payments->take(50)->map(fn (Payment $payment) => [
            'Tanggal' => optional($payment->payment_date)->format('d M Y') ?? '-',
            'Penerima' => $payment->payeeUser?->name ?? '-',
            'Kategori' => 'Honor pelatih',
            'Total' => 'Rp '.number_format((float) ($payment->total_amount ?? $payment->amount), 0, ',', '.'),
            'Dibayar' => 'Rp '.number_format((float) $payment->paid_amount, 0, ',', '.'),
            'Sisa' => 'Rp '.number_format((float) $payment->remaining_amount, 0, ',', '.'),
            'Status' => $payment->status,
        ])->values()->all());
    }

    public function monthlyDues(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $setting = BillingSetting::monthlyTuition();
        $month = $request->integer('month', now()->month);
        $year = $request->integer('year', now()->year);
        $athletes = Athlete::query()->with(['user', 'payments' => fn ($query) => $query->whereMonth('payment_date', $month)->whereYear('payment_date', $year)->where('payment_type', 'TUITION')])->orderBy('created_at')->get();
        $payments = Payment::query()->whereMonth('payment_date', $month)->whereYear('payment_date', $year)->where('payment_type', 'TUITION')->get();
        $paidAthleteIds = $payments->where('remaining_amount', '<=', 0)->pluck('athlete_id')->filter()->unique();

        return $this->renderFeature('Input Iuran', 'Input dan pantau iuran bulanan member langsung dari halaman ini.', [
            ['label' => 'Total Member', 'value' => (string) $athletes->count(), 'tone' => 'info'],
            ['label' => 'Sudah Bayar', 'value' => (string) $paidAthleteIds->count(), 'tone' => 'success'],
            ['label' => 'Belum Bayar', 'value' => (string) max($athletes->count() - $paidAthleteIds->count(), 0), 'tone' => 'danger'],
            ['label' => 'Total Masuk', 'value' => 'Rp '.number_format((float) $payments->sum('paid_amount'), 0, ',', '.'), 'tone' => 'info'],
        ], ['Nama Member', 'No. HP', 'Tipe Iuran', 'Nominal', 'Tanggal Bayar', 'No. Transaksi', 'Aksi'], 'Belum ada data iuran bulan ini.', 'monthly-dues', $athletes->map(function (Athlete $athlete) use ($setting, $payments, $paidAthleteIds, $month, $year): array {
            $payment = $payments->firstWhere('athlete_id', $athlete->athlete_id);
            return [
                'Nama Member' => ($athlete->user?->name ?? $athlete->athlete_id).'\n'.($athlete->athlete_id),
                'No. HP' => $athlete->user?->phone_number ?? $athlete->user?->phone ?? '-',
                'Tipe Iuran' => 'Iuran Bulanan '.Carbon::create($year, $month, 1)->translatedFormat('F Y'),
                'Nominal' => 'Rp '.number_format((float) ($payment?->total_amount ?? $setting->default_amount), 0, ',', '.'),
                'Tanggal Bayar' => optional($payment?->payment_date)->format('d M Y') ?? '-',
                'No. Transaksi' => $payment?->reference_id ?? '-',
                'Aksi' => $paidAthleteIds->contains($athlete->athlete_id) ? 'Lunas' : 'Input / WA',
            ];
        })->values()->all(), ['billingSettings' => ['invoice_day' => $setting->invoice_day, 'invoice_time' => substr((string) $setting->invoice_time, 0, 5), 'default_amount' => (string) $setting->default_amount, 'is_active' => (bool) $setting->is_active]]);
    }

    public function updateBillingSettings(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate(['invoice_day' => ['required', 'integer', 'min:1', 'max:28'], 'invoice_time' => ['required', 'date_format:H:i'], 'default_amount' => ['required', 'numeric', 'min:0'], 'is_active' => ['boolean']]);
        BillingSetting::monthlyTuition()->update(['invoice_day' => $validated['invoice_day'], 'invoice_time' => $validated['invoice_time'].':00', 'default_amount' => $validated['default_amount'], 'is_active' => (bool) ($validated['is_active'] ?? false)]);

        return back()->with('status', 'Monthly tuition billing settings updated.');
    }

    public function generateMonthlyDues(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        Artisan::call('tuition:generate-monthly --force');

        return back()->with('status', trim(Artisan::output()));
    }

    public function members(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $athletes = Athlete::query()->with(['user', 'branch', 'group'])->latest('created_at')->take(100)->get();
        return $this->renderFeature('Manajemen Anggota', 'Total '.$athletes->count().' anggota terdaftar', [], ['No.', 'Anggota', 'Sabuk', 'Kontak', 'Status', 'Aksi'], 'Tidak ada data anggota', 'members', $athletes->values()->map(fn (Athlete $athlete, int $index) => [
            'No.' => (string) ($index + 1),
            'Anggota' => ($athlete->user?->name ?? '-').'\n'.$athlete->athlete_id,
            'Sabuk' => $athlete->geup ?? '-',
            'Kontak' => $athlete->user?->phone_number ?? $athlete->user?->phone ?? '-',
            'Status' => $athlete->user?->status ?? 'Active',
            'Aksi' => 'Profil',
        ])->all());
    }

    public function instructors(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $coaches = Coach::query()->with('user')->latest('created_at')->take(100)->get();
        return $this->renderFeature('Master Pelatih', 'Total '.$coaches->count().' pelatih terdaftar', [], ['No.', 'Pelatih', 'Spesialisasi', 'Sabuk', 'Kontak', 'Status'], 'Data tidak ditemukan', 'instructors', $coaches->values()->map(fn (Coach $coach, int $index) => [
            'No.' => (string) ($index + 1),
            'Pelatih' => ($coach->user?->name ?? '-').'\n'.$coach->coach_id,
            'Spesialisasi' => $coach->specialization ?? '-',
            'Sabuk' => $coach->belt ?? '-',
            'Kontak' => $coach->user?->email ?? '-',
            'Status' => $coach->status ?? 'Active',
        ])->all());
    }

    public function events(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $events = Event::query()->withCount('registrations')->latest('e_date')->take(100)->get();
        return $this->renderFeature('Manajemen Event', 'Total '.$events->count().' event terdaftar', [], ['Event', 'Tanggal & Waktu', 'Pendaftar', 'Biaya', 'Tipe & Visibilitas', 'Aksi'], 'Tidak ada data event', 'events', $events->map(fn (Event $event) => [
            'Event' => $event->e_name,
            'Tanggal & Waktu' => optional($event->e_date)->format('d M Y H:i') ?? '-',
            'Pendaftar' => (string) $event->registrations_count,
            'Biaya' => 'Rp '.number_format((float) $event->entry_fee, 0, ',', '.'),
            'Tipe & Visibilitas' => ($event->level ?? '-').' · '.($event->status ?? '-'),
            'Aksi' => 'Detail',
        ])->values()->all());
    }

    public function eventHistory(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $events = Event::query()->withCount('registrations')->whereDate('e_date', '<', now()->toDateString())->latest('e_date')->take(100)->get();
        return $this->renderFeature('Riwayat Event & UKT', 'Event yang pernah diselenggarakan.', [], ['Event', 'Tanggal', 'Penyelenggara', 'Total Peserta', 'Tipe', 'Aksi'], 'Tidak ada riwayat event', 'event-history', $events->map(fn (Event $event) => [
            'Event' => $event->e_name,
            'Tanggal' => optional($event->e_date)->format('d M Y') ?? '-',
            'Penyelenggara' => $event->organizer ?? '-',
            'Total Peserta' => (string) $event->registrations_count,
            'Tipe' => $event->level ?? '-',
            'Aksi' => 'Detail',
        ])->values()->all());
    }

    public function eventSchedule(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $events = Event::query()->whereIn('status', ['ACTIVE', 'OPEN', 'PUBLISHED'])->orderBy('e_date')->get(['event_id', 'e_name']);
        return $this->renderFeature('Jadwal Pertandingan', 'Publikasi & gate display untuk jadwal pertandingan.', [], ['Mat', 'Waktu', 'Kontingen', 'Partai', 'Status', 'Aksi'], 'Pilih event terlebih dahulu untuk mengaktifkan launcher jadwal.', 'event-schedule', [], ['eventOptions' => $events->map(fn (Event $event) => ['value' => $event->event_id, 'label' => $event->e_name])->values()]);
    }

    public function dailySchedules(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $date = $request->date('date')?->toDateString() ?? now()->toDateString();
        $sessions = TrainingSession::query()->with(['group', 'branch', 'primaryCoach.user'])->withCount(['attendances as present_count' => fn ($query) => $query->where('status', 'PRESENT')])->whereDate('session_date', $date)->where('status', '!=', 'CANCELED')->orderBy('start_time')->get();
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

    public function periodicStats(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $month = $request->integer('month', now()->month);
        $year = $request->integer('year', now()->year);
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();
        $newAthletes = Athlete::query()->whereBetween('created_at', [$start, $end])->count();
        $newCoaches = Coach::query()->whereBetween('created_at', [$start, $end])->count();

        return $this->renderFeature('Dashboard Statistik Per Periode', 'Analisis perkembangan member dan pelatih.', [
            ['label' => 'Pertumbuhan Anggota', 'value' => '+'.$newAthletes.' Member Baru', 'tone' => 'info'],
            ['label' => 'Pertumbuhan Pelatih', 'value' => '+'.$newCoaches.' Pelatih Baru', 'tone' => 'warning'],
            ['label' => 'Total Akhir Periode', 'value' => Athlete::whereDate('created_at', '<=', $end->toDateString())->count().' Member', 'tone' => 'success'],
        ], ['Tanggal', 'Member Baru', 'Pelatih Baru', 'Total'], 'Belum ada pertumbuhan pada periode ini.', 'periodic-stats', []);
    }

    public function locations(Request $request): Response { return $this->redirectToTrainingManagement($request); }
    public function classes(Request $request): Response { return $this->redirectToTrainingManagement($request); }
    public function weeklySchedules(Request $request): Response { return $this->redirectToTrainingManagement($request); }
    public function storeWeeklySchedule(Request $request): RedirectResponse { return redirect()->route('admin.training-management'); }
    public function generateWeeklySessions(Request $request, GenerateWeeklyTrainingSessions $generator): RedirectResponse { return redirect()->route('admin.training-management'); }

    private function redirectToTrainingManagement(Request $request): Response
    {
        $this->authorizeAdmin($request);
        return Inertia::render('TrainingManagementPage', ['redirectTo' => route('admin.training-management')]);
    }

    private function renderFeature(string $title, string $subtitle, array $metrics, array $columns, string $emptyText, string $mode, array $rows = [], array $extra = []): Response
    {
        return Inertia::render('AdminFeaturePage', array_merge(['mode' => $mode, 'title' => $title, 'subtitle' => $subtitle, 'metrics' => $metrics, 'columns' => $columns, 'rows' => $rows, 'emptyText' => $emptyText, 'roleAccess' => 'Admin only', 'todaySessions' => [], 'billingSettings' => null], $extra));
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }
}
