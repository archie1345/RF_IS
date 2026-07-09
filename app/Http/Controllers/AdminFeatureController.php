<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\BillingSetting;
use App\Models\Coach;
use App\Models\CoachAttendance;
use App\Models\Event;
use App\Models\Payment;
use App\Models\TrainingSession;
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
        $attendances = Attendance::query()
            ->with(['athlete.user', 'athlete.branch', 'athlete.group'])
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get();
        $attendanceByAthlete = $attendances->groupBy('athlete_id');

        $athletes = Athlete::query()
            ->with(['user', 'branch', 'group'])
            ->orderBy('athlete_id')
            ->get();

        return $this->renderFeature('Rekap Presensi Atlet', 'Rekap semua presensi atlet per periode. Gunakan query ?from=YYYY-MM-DD&to=YYYY-MM-DD untuk mengganti periode.', [
            ['label' => 'Total Atlet', 'value' => (string) $athletes->count(), 'tone' => 'info'],
            ['label' => 'Total Catatan', 'value' => (string) $attendances->count(), 'tone' => 'neutral'],
            ['label' => 'Hadir', 'value' => (string) $attendances->where('status', 'PRESENT')->count(), 'tone' => 'success'],
            ['label' => 'Tidak Hadir', 'value' => (string) $attendances->whereIn('status', ['ABSENT', 'SICK', 'EXCUSED'])->count(), 'tone' => 'warning'],
        ], ['No', 'Atlet', 'Kelas', 'Lokasi', 'Total', 'Hadir', 'Izin', 'Sakit', 'Alpha', 'Terlambat', 'Persentase'], 'Belum ada data presensi atlet.', 'attendance', $athletes->values()->map(function (Athlete $athlete, int $index) use ($attendanceByAthlete): array {
            $records = $attendanceByAthlete->get($athlete->athlete_id, collect());
            $total = $records->count();
            $present = $records->where('status', 'PRESENT')->count();
            $late = $records->where('status', 'LATE')->count();
            $excused = $records->where('status', 'EXCUSED')->count();
            $sick = $records->where('status', 'SICK')->count();
            $absent = $records->where('status', 'ABSENT')->count();
            $rate = $total > 0 ? round((($present + $late) / $total) * 100) : 0;

            return [
                'No' => (string) ($index + 1),
                'Atlet' => ($athlete->user?->name ?? 'Unknown athlete').'\n'.$athlete->athlete_id,
                'Kelas' => $athlete->group?->group_name ?? '-',
                'Lokasi' => $athlete->branch?->branch_name ?? '-',
                'Total' => (string) $total,
                'Hadir' => (string) $present,
                'Izin' => (string) $excused,
                'Sakit' => (string) $sick,
                'Alpha' => (string) $absent,
                'Terlambat' => (string) $late,
                'Persentase' => $rate.'%',
            ];
        })->all());
    }

    public function instructorAttendance(Request $request): Response
    {
        $this->authorizeAdmin($request);

        $from = $request->date('from')?->startOfDay() ?? now()->startOfMonth();
        $to = $request->date('to')?->endOfDay() ?? now()->endOfDay();
        $coachAttendance = CoachAttendance::query()
            ->with(['coach.user', 'trainingSession.group', 'trainingSession.branch'])
            ->whereHas('trainingSession', fn ($query) => $query->whereBetween('session_date', [$from->toDateString(), $to->toDateString()]))
            ->get();
        $coachAttendanceByCoach = $coachAttendance->groupBy('coach_id');
        $scheduledSessionsByCoach = TrainingSession::query()
            ->whereBetween('session_date', [$from->toDateString(), $to->toDateString()])
            ->whereNotNull('coach_id')
            ->where('status', '!=', 'CANCELED')
            ->get()
            ->groupBy('coach_id');
        $coaches = Coach::query()->with('user')->orderBy('coach_id')->get();

        return $this->renderFeature('Rekap Presensi Coach', 'Rekap semua presensi coach per periode berdasarkan jadwal dan coach attendance. Gunakan query ?from=YYYY-MM-DD&to=YYYY-MM-DD.', [
            ['label' => 'Total Coach', 'value' => (string) $coaches->count(), 'tone' => 'info'],
            ['label' => 'Jadwal Coach', 'value' => (string) $scheduledSessionsByCoach->flatten()->count(), 'tone' => 'neutral'],
            ['label' => 'Mengajar', 'value' => (string) $coachAttendance->where('status', 'TEACH')->count(), 'tone' => 'success'],
            ['label' => 'Tidak Mengajar', 'value' => (string) $coachAttendance->where('status', 'NOT_TEACH')->count(), 'tone' => 'warning'],
        ], ['No', 'Coach', 'Jadwal', 'Mengajar', 'Tidak Mengajar', 'Belum Dicatat', 'Terakhir Check', 'Persentase'], 'Belum ada data presensi coach.', 'instructor-attendance', $coaches->values()->map(function (Coach $coach, int $index) use ($coachAttendanceByCoach, $scheduledSessionsByCoach): array {
            $records = $coachAttendanceByCoach->get($coach->coach_id, collect());
            $scheduled = $scheduledSessionsByCoach->get($coach->coach_id, collect())->count();
            $teach = $records->where('status', 'TEACH')->count();
            $notTeach = $records->where('status', 'NOT_TEACH')->count();
            $unrecorded = max($scheduled - $records->count(), 0);
            $rate = $scheduled > 0 ? round(($teach / $scheduled) * 100) : 0;
            $lastChecked = $records->sortByDesc('checked_at')->first()?->checked_at;

            return [
                'No' => (string) ($index + 1),
                'Coach' => ($coach->user?->name ?? 'Unknown coach').'\n'.$coach->coach_id,
                'Jadwal' => (string) $scheduled,
                'Mengajar' => (string) $teach,
                'Tidak Mengajar' => (string) $notTeach,
                'Belum Dicatat' => (string) $unrecorded,
                'Terakhir Check' => $lastChecked ? Carbon::parse((string) $lastChecked)->format('d M Y H:i') : '-',
                'Persentase' => $rate.'%',
            ];
        })->all());
    }

    public function payments(Request $request): Response
    {
        $this->authorizeAdmin($request);

        $payments = Payment::query()
            ->with(['athlete.user', 'billableUser', 'payeeUser'])
            ->latest('payment_date')
            ->latest('payment_id')
            ->take(200)
            ->get();
        $setting = BillingSetting::monthlyTuition();
        $tuitionPayments = $payments->where('payment_type', 'TUITION');
        $incomePayments = $payments->where('bill_kind', '!=', 'PAYROLL');
        $outputPayments = $payments->where('bill_kind', 'PAYROLL');

        return $this->renderFeature('Keuangan', 'Semua finance digabung di sini: invoice/tagihan, bukti pembayaran, uang masuk, uang keluar, iuran bulanan, dan export invoice.', [
            ['label' => 'Invoice Aktif', 'value' => (string) $payments->count(), 'tone' => 'info'],
            ['label' => 'Perlu Verifikasi', 'value' => (string) $payments->whereIn('proof_status', ['PENDING', 'SUBMITTED'])->count(), 'tone' => 'warning'],
            ['label' => 'Uang Masuk', 'value' => 'Rp '.number_format((float) $incomePayments->sum('paid_amount'), 0, ',', '.'), 'tone' => 'success'],
            ['label' => 'Uang Keluar', 'value' => 'Rp '.number_format((float) $outputPayments->sum('paid_amount'), 0, ',', '.'), 'tone' => 'danger'],
        ], ['ID & Tanggal', 'Pihak', 'Kategori', 'Tagihan', 'Terbayar', 'Sisa', 'Bukti', 'Status', 'Aksi'], 'Belum ada data keuangan.', 'payments', $payments->map(fn (Payment $payment) => [
            'ID & Tanggal' => '#'.$payment->payment_id.' · '.(optional($payment->payment_date)->format('d M Y') ?? '-'),
            'Pihak' => $payment->athlete?->user?->name ?? $payment->billableUser?->name ?? $payment->payeeUser?->name ?? '-',
            'Kategori' => trim(($payment->bill_kind ?? 'BILL').' · '.$payment->payment_type.' · '.($payment->notes ?? '')),
            'Tagihan' => 'Rp '.number_format((float) ($payment->total_amount ?? $payment->amount), 0, ',', '.'),
            'Terbayar' => 'Rp '.number_format((float) $payment->paid_amount, 0, ',', '.'),
            'Sisa' => 'Rp '.number_format((float) $payment->remaining_amount, 0, ',', '.'),
            'Bukti' => $payment->proof_path ? 'Ada' : '-',
            'Status' => trim($payment->status.' / '.($payment->proof_status ?? '-')),
            'Aksi' => 'Payment Center / Export Invoice',
        ])->values()->all(), [
            'billingSettings' => [
                'invoice_day' => $setting->invoice_day,
                'invoice_time' => substr((string) $setting->invoice_time, 0, 5),
                'default_amount' => (string) $setting->default_amount,
                'is_active' => (bool) $setting->is_active,
            ],
            'financeSummary' => [
                'tuition_count' => $tuitionPayments->count(),
                'receivable' => (float) $incomePayments->sum('remaining_amount'),
                'payable' => (float) $outputPayments->sum('remaining_amount'),
            ],
        ]);
    }

    public function updateBillingSettings(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate(['invoice_day' => ['required', 'integer', 'min:1', 'max:28'], 'invoice_time' => ['required', 'date_format:H:i'], 'default_amount' => ['required', 'numeric', 'min:0'], 'is_active' => ['boolean']]);
        BillingSetting::monthlyTuition()->update(['invoice_day' => $validated['invoice_day'], 'invoice_time' => $validated['invoice_time'].':00', 'default_amount' => $validated['default_amount'], 'is_active' => (bool) ($validated['is_active'] ?? false)]);

        return redirect()->route('admin.payments')->with('status', 'Monthly tuition billing settings updated.');
    }

    public function generateMonthlyDues(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        Artisan::call('tuition:generate-monthly --force');

        return redirect()->route('admin.payments')->with('status', trim(Artisan::output()));
    }

    public function financeIncome(Request $request): Response { return $this->payments($request); }
    public function financeOutput(Request $request): Response { return $this->payments($request); }
    public function monthlyDues(Request $request): Response { return $this->payments($request); }

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
        return $this->renderFeature('Manajemen Event / UKT', 'Event internal dan UKT. Jadwal pertandingan terpisah sudah dihapus dari navigasi.', [], ['Event', 'Tanggal & Waktu', 'Pendaftar', 'Biaya', 'Tipe & Visibilitas', 'Aksi'], 'Tidak ada data event', 'events', $events->map(fn (Event $event) => [
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

    public function eventSchedule(Request $request): Response { return $this->events($request); }

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
        return Inertia::render('AdminFeaturePage', ['mode' => 'periodic-stats', 'title' => 'Laporan dinonaktifkan', 'subtitle' => 'Halaman laporan sudah dihapus dari navigasi.', 'metrics' => [], 'columns' => [], 'rows' => [], 'emptyText' => '', 'roleAccess' => 'Admin only']);
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
