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
        $attendances = Attendance::query()
            ->with(['athlete.user', 'trainingSession.group', 'trainingSession.branch'])
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->latest('date')
            ->latest('checked_in_at')
            ->take(100)
            ->get();

        return $this->renderFeature('Absensi Latihan', 'Kelola presensi anggota per kelas', [
            ['label' => 'Total Absensi', 'value' => (string) $attendances->count(), 'tone' => 'neutral'],
            ['label' => 'Total Hadir', 'value' => (string) $attendances->where('status', 'PRESENT')->count(), 'tone' => 'success'],
            ['label' => 'Total Izin', 'value' => (string) $attendances->where('status', 'EXCUSED')->count(), 'tone' => 'info'],
            ['label' => 'Total Sakit', 'value' => (string) $attendances->where('status', 'SICK')->count(), 'tone' => 'warning'],
        ], ['No', 'Tanggal', 'Member / Pelatih', 'Kelas', 'Check-In', 'Check-Out', 'Status', 'Aksi'], 'Tidak ada data absensi', 'attendance', $attendances->values()->map(fn (Attendance $attendance, int $index) => [
            'No' => (string) ($index + 1),
            'Tanggal' => optional($attendance->date)->format('d M Y') ?? '-',
            'Member / Pelatih' => $attendance->athlete?->user?->name ?? $attendance->athlete_id,
            'Kelas' => $attendance->trainingSession?->group?->group_name ?? $attendance->trainingSession?->title ?? '-',
            'Check-In' => optional($attendance->checked_in_at)->format('H:i') ?? '-',
            'Check-Out' => '-',
            'Status' => $attendance->status,
            'Aksi' => '-',
        ])->all());
    }

    public function instructorAttendance(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $sessions = TrainingSession::query()
            ->with(['primaryCoach.user', 'group'])
            ->whereNotNull('coach_id')
            ->whereMonth('session_date', $request->integer('month', now()->month))
            ->whereYear('session_date', $request->integer('year', now()->year))
            ->latest('session_date')
            ->take(100)
            ->get();

        return $this->renderFeature('Presensi Pelatih', 'Rekapitulasi kehadiran untuk pembayaran honor', [
            ['label' => 'Total Jadwal', 'value' => (string) $sessions->count(), 'tone' => 'info'],
            ['label' => 'Pelatih Terjadwal', 'value' => (string) $sessions->pluck('coach_id')->filter()->unique()->count(), 'tone' => 'success'],
        ], ['No', 'ID', 'Tanggal', 'Pelatih', 'Kelas', 'Jam', 'Status', 'Aksi'], 'Tidak ada data presensi ditemukan', 'instructor-attendance', $sessions->values()->map(fn (TrainingSession $session, int $index) => [
            'No' => (string) ($index + 1),
            'ID' => (string) $session->training_session_id,
            'Tanggal' => optional($session->session_date)->format('d M Y') ?? '-',
            'Pelatih' => $session->primaryCoach?->user?->name ?? '-',
            'Kelas' => $session->group?->group_name ?? $session->title,
            'Jam' => substr((string) $session->start_time, 0, 5).' - '.substr((string) $session->end_time, 0, 5),
            'Status' => $session->status,
            'Aksi' => '-',
        ])->all());
    }

    public function payments(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $payments = Payment::query()->with(['athlete.user', 'billableUser'])->latest('payment_date')->latest('payment_id')->take(100)->get();

        return $this->renderFeature('Validasi Keuangan', 'Verifikasi bukti pembayaran yang masuk.', [
            ['label' => 'Perlu Verifikasi', 'value' => (string) $payments->whereIn('proof_status', ['PENDING', 'SUBMITTED'])->count(), 'tone' => 'warning'],
            ['label' => 'Data Lunas', 'value' => (string) $payments->where('remaining_amount', '<=', 0)->count(), 'tone' => 'success'],
            ['label' => 'Ditolak/Gagal', 'value' => (string) $payments->whereIn('status', ['FAILED', 'REJECTED'])->count(), 'tone' => 'danger'],
            ['label' => 'Total Masuk', 'value' => 'Rp '.number_format((float) $payments->sum('paid_amount'), 0, ',', '.'), 'tone' => 'info'],
        ], ['ID & Tanggal', 'Member', 'Tipe & Keterangan', 'Nominal', 'Metode', 'Aksi'], 'Tidak ada data pembayaran pending.', 'payments', $payments->map(fn (Payment $payment) => [
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

        return $this->renderFeature('Keuangan Masuk', 'Ledger uang masuk dari tagihan anggota. Bukti pembayaran tetap dikelola di Bills and Payment Proof.', [
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

        return $this->renderFeature('Keuangan Keluar', 'Ledger uang keluar untuk honor pelatih dan pengeluaran operasional.', [
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
        })->values()->all(), [
            'billingSettings' => [
                'invoice_day' => $setting->invoice_day,
                'invoice_time' => substr((string) $setting->invoice_time, 0, 5),
                'default_amount' => (string) $setting->default_amount,
                'is_active' => (bool) $setting->is_active,
            ],
        ]);
    }

    public function updateBillingSettings(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate(['invoice_day' => ['required', 'integer', 'min:1', 'max:28'], 'invoice_time' => ['required', 'date_format:H:i'], 'default_amount' => ['required', 'numeric', 'min:0'], 'is_active' => ['boolean']]);
        BillingSetting::monthlyTuition()->update(['invoice_day' => $validated['invoice_day'], 'invoice_time' => $validated['invoice_time'].':00', 'default_amount' => $validated['default_amount'], 'is_active' => (bool) ($validated['is_active'] ?? false)]);
        return back()->with('status', 'Monthly tuition billing settings updated.');
    }

    public function generateMonthlyDues(Request $request): RedirectResponse { $this->authorizeAdmin($request); Artisan::call('tuition:generate-monthly --force'); return back()->with('status', trim(Artisan::output())); }

    public function members(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $athletes = Athlete::query()->with(['user', 'branch', 'group'])->latest('created_at')->take(100)->get();
        return $this->renderFeature('Manajemen Anggota', 'DOJANG: RTFCM · Total '.$athletes->count().' anggota terdaftar', [], ['No.', 'Anggota', 'Pribadi', 'Sabuk', 'Kontak', 'Dokumen', 'Status', 'Aksi'], 'Tidak ada data anggota', 'members', $athletes->values()->map(fn (Athlete $athlete, int $index) => [
            'No.' => (string) ($index + 1),
            'Anggota' => ($athlete->user?->name ?? '-').'\n'.$athlete->athlete_id.'\nBergabung '.optional($athlete->created_at)->format('Y-m-d'),
            'Pribadi' => 'BB: '.($athlete->weight_kg ?? '-').' KG · TB: '.($athlete->height_cm ?? '-').' CM',
            'Sabuk' => $athlete->geup ?? '-',
            'Kontak' => ($athlete->alamat ?? '-').'\n'.($athlete->user?->phone_number ?? $athlete->user?->phone ?? '-'),
            'Dokumen' => filled($athlete->getRawOriginal('nik_hash')) || filled($athlete->getRawOriginal('nik_ciphertext')) ? 'Lengkap' : '-',
            'Status' => $athlete->user?->status ?? 'Active',
            'Aksi' => 'Profil',
        ])->all());
    }

    public function instructors(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $coaches = Coach::query()->with('user')->latest('created_at')->take(100)->get();
        return $this->renderFeature('Master Pelatih', 'DOJANG: RTFCM · Total '.$coaches->count().' pelatih terdaftar', [], ['No.', 'Pelatih', 'Spesialisasi', 'Sabuk', 'Sertifikat', 'Kontak', 'Status', 'Aksi'], 'Data tidak ditemukan', 'instructors', $coaches->values()->map(fn (Coach $coach, int $index) => [
            'No.' => (string) ($index + 1),
            'Pelatih' => ($coach->user?->name ?? '-').'\n'.$coach->coach_id,
            'Spesialisasi' => $coach->specialization ?? '-',
            'Sabuk' => $coach->belt ?? '-',
            'Sertifikat' => '-',
            'Kontak' => $coach->user?->email ?? '-',
            'Status' => $coach->status ?? 'Active',
            'Aksi' => 'Profil',
        ])->all());
    }

    public function events(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $events = Event::query()->withCount('registrations')->latest('e_date')->take(100)->get();
        return $this->renderFeature('Manajemen Event', 'DOJANG: RTFCM · Total '.$events->count().' event terdaftar', [], ['Event', 'Tanggal & Waktu', 'Batas Pendaftaran', 'Pendaftar', 'Biaya', 'Tipe & Visibilitas', 'Aksi'], 'Tidak ada data event', 'events', $events->map(fn (Event $event) => [
            'Event' => $event->e_name,
            'Tanggal & Waktu' => optional($event->e_date)->format('d M Y H:i') ?? '-',
            'Batas Pendaftaran' => '-',
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
        return $this->renderFeature('Riwayat Event & UKT', 'Menampilkan event yang pernah diikuti/diselenggarakan', [], ['Event', 'Tanggal', 'Penyelenggara', 'Peserta Anda', 'Total Peserta', 'Tipe', 'Aksi'], 'Tidak ada riwayat event', 'event-history', $events->map(fn (Event $event) => [
            'Event' => $event->e_name,
            'Tanggal' => optional($event->e_date)->format('d M Y') ?? '-',
            'Penyelenggara' => $event->organizer ?? '-',
            'Peserta Anda' => '-',
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

    public function locations(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $branches = Branch::query()->withCount(['athletes', 'groups'])->orderBy('branch_name')->get();

        return $this->renderFeature('Manajemen Lokasi', 'Kelola lokasi latihan, radius absensi, peta, dan zona waktu.', [
            ['label' => 'Total Lokasi', 'value' => (string) $branches->count(), 'tone' => 'info'],
            ['label' => 'Lokasi Aktif', 'value' => (string) $branches->where('is_active', true)->count(), 'tone' => 'success'],
            ['label' => 'Kelas Terhubung', 'value' => (string) $branches->sum('groups_count'), 'tone' => 'warning'],
        ], ['Nama Lokasi & Kelas', 'Alamat', 'Status', 'Aksi'], 'Tidak ada data lokasi', 'locations', [], [
            'locations' => $branches->map(fn (Branch $branch) => [
                'id' => $branch->branch_id,
                'name' => $branch->branch_name,
                'location' => $branch->location,
                'address' => $branch->address ?? $branch->location,
                'city' => $branch->city,
                'province' => $branch->province,
                'latitude' => $branch->latitude,
                'longitude' => $branch->longitude,
                'attendance_radius_meters' => $branch->attendance_radius_meters ?? 100,
                'timezone' => $branch->timezone ?? 'Asia/Jakarta',
                'is_active' => (bool) ($branch->is_active ?? true),
                'groups_count' => $branch->groups_count,
            ])->values(),
        ]);
    }

    public function classes(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $groups = Group::query()->with(['coach.user', 'branch'])->withCount('athletes')->orderBy('group_name')->get();

        return $this->renderFeature('Manajemen Kelas', 'Kelola kelas, kapasitas, dan jadwal latihan rutin.', [
            ['label' => 'Total Kelas', 'value' => (string) $groups->count(), 'tone' => 'info'],
            ['label' => 'Kelas Aktif', 'value' => (string) $groups->where('is_active', true)->count(), 'tone' => 'success'],
            ['label' => 'Siswa Terdaftar', 'value' => (string) $groups->sum('athletes_count'), 'tone' => 'warning'],
        ], ['Kelas', 'Instruktur', 'Jadwal', 'Kapasitas', 'Status', 'Aksi'], 'Tidak ada data kelas', 'classes', [], [
            'classes' => $groups->map(fn (Group $group) => [
                'id' => $group->group_id,
                'name' => $group->group_name,
                'class_type' => $group->class_type ?? 'Beginner',
                'coach_id' => $group->coach_id,
                'coach' => $group->coach?->user?->name ?? 'TBA',
                'branch_id' => $group->branch_id,
                'branch' => $group->branch?->branch_name ?? 'Lokasi TBA',
                'day_of_week' => $group->day_of_week ?? 1,
                'schedule' => $this->dayName((int) ($group->day_of_week ?? 1)),
                'time' => ($group->start_time ? substr((string) $group->start_time, 0, 5) : '-').' - '.($group->end_time ? substr((string) $group->end_time, 0, 5) : '-'),
                'start_time' => $group->start_time ? substr((string) $group->start_time, 0, 5) : '',
                'end_time' => $group->end_time ? substr((string) $group->end_time, 0, 5) : '',
                'capacity' => $group->capacity ?? 20,
                'athletes_count' => $group->athletes_count,
                'min_belt' => $group->min_belt ?? '10th Geup - White Belt',
                'description' => $group->description,
                'is_active' => (bool) ($group->is_active ?? true),
            ])->values(),
            'branchOptions' => Branch::query()->orderBy('branch_name')->get(['branch_id as value', 'branch_name as label']),
            'coachOptions' => Coach::query()->with('user:id,name')->get()->map(fn (Coach $coach) => ['value' => $coach->coach_id, 'label' => $coach->user?->name ?? 'Unknown coach'])->sortBy('label')->values(),
        ]);
    }

    public function weeklySchedules(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $sessions = TrainingSession::query()->whereBetween('session_date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])->where('status', '!=', 'CANCELED')->get();
        $weeklySchedules = WeeklyTrainingSchedule::query()->with(['branch', 'group', 'coach.user'])->orderBy('day_of_week')->orderBy('start_time')->get();

        return Inertia::render('AdminFeaturePage', [
            'mode' => 'weekly-schedule', 'title' => 'Jadwal Mingguan', 'subtitle' => 'Jadwal latihan rutin RTFCM. Scheduler otomatis membuat training session aktual dari template mingguan aktif.',
            'metrics' => [
                ['label' => 'Template Aktif', 'value' => (string) $weeklySchedules->where('is_active', true)->count(), 'tone' => 'success'],
                ['label' => 'Sesi Minggu Ini', 'value' => (string) $sessions->count(), 'tone' => 'info'],
                ['label' => 'Hari Latihan', 'value' => (string) $sessions->pluck('session_date')->unique()->count(), 'tone' => 'warning'],
                ['label' => 'Sesi Hari Ini', 'value' => (string) $sessions->where('session_date', now()->toDateString())->count(), 'tone' => 'danger'],
            ],
            'columns' => [], 'rows' => [], 'emptyText' => '', 'roleAccess' => 'Admin only',
            'todaySessions' => $sessions->map(fn (TrainingSession $session) => ['title' => $session->title, 'time' => substr((string) $session->start_time, 0, 5).' - '.substr((string) $session->end_time, 0, 5), 'location' => $session->location ?? 'RTFCM', 'date' => Carbon::parse((string) $session->session_date)->format('Y-m-d')])->values(),
            'billingSettings' => null,
            'weeklySchedules' => $weeklySchedules->map(fn (WeeklyTrainingSchedule $schedule) => ['id' => $schedule->weekly_training_schedule_id, 'title' => $schedule->title, 'branch' => $schedule->branch?->branch_name ?? '-', 'group' => $schedule->group?->group_name ?? 'All groups', 'coach' => $schedule->coach?->user?->name ?? '-', 'day_of_week' => $schedule->day_of_week, 'time' => substr((string) $schedule->start_time, 0, 5).' - '.substr((string) $schedule->end_time, 0, 5), 'location' => $schedule->location ?? $schedule->branch?->location ?? '-', 'is_active' => (bool) $schedule->is_active])->values(),
            'branchOptions' => Branch::query()->orderBy('branch_name')->get(['branch_id as value', 'branch_name as label']),
            'groupOptions' => Group::query()->orderBy('group_name')->get(['group_id as value', 'group_name as label']),
            'coachOptions' => Coach::query()->with('user:id,name')->get()->map(fn (Coach $coach) => ['value' => $coach->coach_id, 'label' => $coach->user?->name ?? 'Unknown coach'])->sortBy('label')->values(),
        ]);
    }

    public function storeWeeklySchedule(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate(['title' => ['required', 'string', 'max:150'], 'branch_id' => ['required', 'exists:branches,branch_id'], 'group_id' => ['nullable', 'exists:class_groups,group_id'], 'coach_id' => ['nullable', 'exists:coaches,coach_id'], 'day_of_week' => ['required', 'integer', 'between:1,7'], 'start_time' => ['required', 'date_format:H:i'], 'end_time' => ['required', 'date_format:H:i', 'after:start_time'], 'location' => ['nullable', 'string', 'max:255'], 'is_active' => ['boolean']]);
        WeeklyTrainingSchedule::query()->create([...$validated, 'is_active' => (bool) ($validated['is_active'] ?? true)]);
        return back()->with('status', 'Weekly training schedule saved. The scheduler will generate dated sessions automatically.');
    }

    public function generateWeeklySessions(Request $request, GenerateWeeklyTrainingSessions $generator): RedirectResponse { $this->authorizeAdmin($request); $result = $generator->handle(now()->startOfWeek(), now()->endOfWeek()); return back()->with('status', "Generated {$result['created']} weekly sessions. Skipped {$result['skipped']} duplicates."); }

    public function dailySchedules(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $date = $request->date('date')?->toDateString() ?? now()->toDateString();
        $sessions = TrainingSession::query()->with(['group', 'branch', 'primaryCoach.user'])->withCount(['attendances as present_count' => fn ($query) => $query->where('status', 'PRESENT')])->whereDate('session_date', $date)->where('status', '!=', 'CANCELED')->orderBy('start_time')->get();
        return $this->renderFeature('Jadwal Latihan Harian', 'Pantau pelaksanaan kelas dan kehadiran anggota di seluruh unit hari ini.', [
            ['label' => 'Total Kelas', 'value' => (string) $sessions->count(), 'tone' => 'info'],
            ['label' => 'Kelas Berjalan', 'value' => (string) $sessions->where('status', 'IN_PROGRESS')->count(), 'tone' => 'success'],
            ['label' => 'Siswa Terlibat', 'value' => (string) $sessions->sum('present_count'), 'tone' => 'warning'],
            ['label' => 'Pelatih Hadir', 'value' => (string) $sessions->pluck('coach_id')->filter()->unique()->count(), 'tone' => 'danger'],
        ], ['Waktu Sesi', 'Nama Kelas & Tipe', 'Pelatih (Check-in)', 'Siswa Hadir', 'Status'], 'Tidak ada sesi hari ini.', 'daily-schedules', $sessions->map(fn (TrainingSession $session) => [
            'Waktu Sesi' => substr((string) $session->start_time, 0, 5).' - '.substr((string) $session->end_time, 0, 5).'\n'.($session->location ?? $session->branch?->branch_name ?? '-'),
            'Nama Kelas & Tipe' => ($session->group?->group_name ?? $session->title).'\n'.($session->group?->class_type ?? '-'),
            'Pelatih (Check-in)' => $session->primaryCoach?->user?->name ?? 'Belum ada pelatih check-in',
            'Siswa Hadir' => (string) $session->present_count.' member',
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
        $newAthletes = Athlete::query()->with('user')->whereBetween('created_at', [$start, $end])->get();
        $newCoaches = Coach::query()->with('user')->whereBetween('created_at', [$start, $end])->get();
        $rows = collect(range(1, $start->daysInMonth))->map(fn (int $day) => [
            'Tanggal' => (string) $day,
            'Member Baru' => (string) $newAthletes->filter(fn (Athlete $athlete) => $athlete->created_at?->day === $day)->count(),
            'Pelatih Baru' => (string) $newCoaches->filter(fn (Coach $coach) => $coach->created_at?->day === $day)->count(),
            'Total' => (string) (Athlete::whereDate('created_at', '<=', $start->copy()->day($day)->toDateString())->count()),
        ])->all();

        return $this->renderFeature('Dashboard Statistik Per Periode', 'Analisis laju perkembangan Member, Pelatih, dan Dojang secara bulanan dengan sistem komparasi Like-for-Like.', [
            ['label' => 'Pertumbuhan Anggota', 'value' => '+'.$newAthletes->count().' Member Baru', 'tone' => 'info'],
            ['label' => 'Pertumbuhan Pelatih', 'value' => '+'.$newCoaches->count().' Pelatih Baru', 'tone' => 'warning'],
            ['label' => 'Total Akhir Periode', 'value' => Athlete::whereDate('created_at', '<=', $end->toDateString())->count().' Member', 'tone' => 'success'],
        ], ['Tanggal', 'Member Baru', 'Pelatih Baru', 'Total'], 'Belum ada pertumbuhan pada periode ini.', 'periodic-stats', $rows);
    }

    private function renderFeature(string $title, string $subtitle, array $metrics, array $columns, string $emptyText, string $mode, array $rows = [], array $extra = []): Response
    {
        return Inertia::render('AdminFeaturePage', array_merge(['mode' => $mode, 'title' => $title, 'subtitle' => $subtitle, 'metrics' => $metrics, 'columns' => $columns, 'rows' => $rows, 'emptyText' => $emptyText, 'roleAccess' => 'Admin only', 'todaySessions' => [], 'billingSettings' => null], $extra));
    }

    private function dayName(int $day): string
    {
        return [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'][$day] ?? '-';
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }
}
