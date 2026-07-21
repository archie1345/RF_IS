<?php

namespace App\Http\Controllers\Admin\Features;

use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Coach;
use App\Models\CoachAttendance;
use App\Models\TrainingSession;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminAttendanceReportController extends BaseAdminFeatureController
{
    public function athletes(Request $request): Response
    {
        $this->authorizeAdmin($request);
        [$from, $to, $month] = $this->resolvePeriod($request);

        $attendances = Attendance::query()
            ->with(['athlete.user', 'athlete.branch', 'athlete.group'])
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get();
        $attendanceByAthlete = $attendances->groupBy('athlete_id');

        $athletes = Athlete::query()
            ->with(['user', 'branch', 'group'])
            ->orderBy('athlete_id')
            ->get();

        return $this->renderAttendanceReport(
            'Rekap Presensi Atlet',
            'Rekap presensi atlet berbasis bulan. Gunakan Bulan untuk laporan utama',
            [
                ['label' => 'Total Atlet', 'value' => (string) $athletes->count(), 'tone' => 'info'],
                ['label' => 'Total Catatan', 'value' => (string) $attendances->count(), 'tone' => 'neutral'],
                ['label' => 'Hadir', 'value' => (string) $attendances->where('status', 'PRESENT')->count(), 'tone' => 'success'],
                ['label' => 'Alpha', 'value' => (string) $attendances->where('status', 'ABSENT')->count(), 'tone' => 'warning'],
            ],
            ['No', 'Atlet', 'Kelas', 'Total', 'Hadir', 'Izin', 'Sakit', 'Alpha', 'Terlambat', 'Persentase', 'Status'],
            'Belum ada data presensi atlet.',
            'attendance',
            $athletes->values()->map(function (Athlete $athlete, int $index) use ($attendanceByAthlete): array {
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
                    'Total' => (string) $total,
                    'Hadir' => (string) $present,
                    'Izin' => (string) $excused,
                    'Sakit' => (string) $sick,
                    'Alpha' => (string) $absent,
                    'Terlambat' => (string) $late,
                    'Persentase' => $rate.'%',
                    'Status' => $this->attendanceRateStatus($rate, $total),
                ];
            })->all(),
            $from,
            $to,
            $month,
            route('admin.attendance.export'),
        );
    }

    public function coaches(Request $request): Response
    {
        $this->authorizeAdmin($request);
        [$from, $to, $month] = $this->resolvePeriodUntilToday($request);

        $coachAttendance = CoachAttendance::query()
            ->with(['coach.user'])
            ->whereHas('trainingSession', fn ($query) => $query->whereBetween('session_date', [$from->toDateString(), $to->toDateString()]))
            ->get();
        $coachAttendanceByCoach = $coachAttendance->groupBy('coach_id');
        $coaches = Coach::query()->with('user')->orderBy('coach_id')->get();

        $sessionOptions = TrainingSession::query()
            ->whereDate('session_date', '<=', now()->toDateString())
            ->where('status', '!=', 'CANCELED')
            ->orderByDesc('session_date')
            ->orderByDesc('start_time')
            ->limit(80)
            ->get(['training_session_id', 'title', 'session_date', 'start_time', 'end_time'])
            ->map(fn (TrainingSession $session): array => [
                'value' => $session->training_session_id,
                'label' => $this->sessionOptionLabel($session),
            ])
            ->values();

        return $this->renderAttendanceReport(
            'Rekap Presensi Coach',
            'Rekap presensi coach hanya menghitung catatan sampai hari ini. Data masa depan tidak dimasukkan.',
            [
                ['label' => 'Total Coach', 'value' => (string) $coaches->count(), 'tone' => 'info'],
                ['label' => 'Total Catatan', 'value' => (string) $coachAttendance->count(), 'tone' => 'neutral'],
                ['label' => 'Mengajar', 'value' => (string) $coachAttendance->where('status', 'TEACH')->count(), 'tone' => 'success'],
                ['label' => 'Tidak Mengajar', 'value' => (string) $coachAttendance->where('status', 'NOT_TEACH')->count(), 'tone' => 'warning'],
            ],
            ['No', 'Coach', 'Total Catatan', 'Mengajar', 'Tidak Mengajar', 'Persentase', 'Status'],
            'Belum ada data presensi coach sampai periode ini.',
            'instructor-attendance',
            $coaches->values()->map(function (Coach $coach, int $index) use ($coachAttendanceByCoach): array {
                $records = $coachAttendanceByCoach->get($coach->coach_id, collect());
                $total = $records->count();
                $teach = $records->where('status', 'TEACH')->count();
                $notTeach = $records->where('status', 'NOT_TEACH')->count();
                $rate = $total > 0 ? round(($teach / $total) * 100) : 0;

                return [
                    'No' => (string) ($index + 1),
                    'Coach' => ($coach->user?->name ?? 'Unknown coach').'\n'.$coach->coach_id,
                    'Total Catatan' => (string) $total,
                    'Mengajar' => (string) $teach,
                    'Tidak Mengajar' => (string) $notTeach,
                    'Persentase' => $rate.'%',
                    'Status' => $this->attendanceRateStatus($rate, $total),
                ];
            })->all(),
            $from,
            $to,
            $month,
            route('admin.instructor-attendance.export'),
            [
                'manualCoachAttendanceUrl' => route('admin.instructor-attendance.manual'),
                'coachOptions' => $coaches
                    ->map(fn (Coach $coach): array => ['value' => $coach->coach_id, 'label' => $coach->user?->name ?? 'Unknown coach'])
                    ->sortBy('label')
                    ->values(),
                'sessionOptions' => $sessionOptions,
            ],
        );
    }

    public function storeCoachAttendance(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'coach_id' => ['required', 'exists:coaches,coach_id'],
            'training_session_id' => [
                'required',
                Rule::exists('training_sessions', 'training_session_id')->where(fn ($query) => $query->whereDate('session_date', '<=', now()->toDateString())),
            ],
            'status' => ['required', Rule::in(['TEACH', 'NOT_TEACH'])],
        ]);

        CoachAttendance::query()->updateOrCreate(
            [
                'training_session_id' => $validated['training_session_id'],
                'coach_id' => $validated['coach_id'],
            ],
            [
                'status' => $validated['status'],
                'checked_at' => now(),
            ],
        );

        return back()->with('status', 'Coach attendance saved.');
    }

    public function exportAthletes(Request $request): BinaryFileResponse
    {
        $this->authorizeAdmin($request);
        [$from, $to] = $this->resolvePeriod($request);

        $records = Attendance::query()
            ->with(['athlete.user', 'athlete.group', 'trainingSession.group'])
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->orderBy('athlete_id')
            ->get()
            ->values()
            ->map(fn (Attendance $attendance, int $index): array => [
                (string) ($index + 1),
                optional($attendance->date)->format('d/m/Y') ?? '-',
                $attendance->athlete?->user?->name ?? $attendance->athlete_id ?? '-',
                $attendance->trainingSession?->group?->group_name ?? $attendance->athlete?->group?->group_name ?? '-',
                $attendance->checked_in_at ? Carbon::parse((string) $attendance->checked_in_at)->format('H:i') : '-',
                '-',
                $this->athleteStatusLabel((string) $attendance->status),
            ])->all();

        return $this->downloadAttendanceWorkbook('LAPORAN ABSENSI ATLET', $from, $to, $records, $this->exportFilename('presensi-atlet', $from, $to));
    }

    public function exportCoaches(Request $request): BinaryFileResponse
    {
        $this->authorizeAdmin($request);
        [$from, $to] = $this->resolvePeriodUntilToday($request);

        $recordsByCoach = CoachAttendance::query()
            ->with(['coach.user'])
            ->whereHas('trainingSession', fn ($query) => $query->whereBetween('session_date', [$from->toDateString(), $to->toDateString()]))
            ->get()
            ->groupBy('coach_id');

        $records = Coach::query()
            ->with('user')
            ->orderBy('coach_id')
            ->get()
            ->values()
            ->map(function (Coach $coach, int $index) use ($recordsByCoach): array {
                $coachRecords = $recordsByCoach->get($coach->coach_id, collect());
                $total = $coachRecords->count();
                $teach = $coachRecords->where('status', 'TEACH')->count();
                $notTeach = $coachRecords->where('status', 'NOT_TEACH')->count();
                $rate = $total > 0 ? round(($teach / $total) * 100) : 0;

                return [
                    (string) ($index + 1),
                    $coach->user?->name ?? $coach->coach_id ?? '-',
                    (string) $total,
                    (string) $teach,
                    (string) $notTeach,
                    $rate.'%',
                    $this->attendanceRateStatus($rate, $total),
                ];
            })->all();

        return $this->downloadAttendanceWorkbook('LAPORAN ABSENSI PELATIH', $from, $to, $records, $this->exportFilename('presensi-pelatih', $from, $to), ['No', 'Coach', 'Total Catatan', 'Mengajar', 'Tidak Mengajar', 'Persentase', 'Status']);
    }

    private function renderAttendanceReport(string $title, string $subtitle, array $metrics, array $columns, string $emptyText, string $mode, array $rows, CarbonInterface $from, CarbonInterface $to, string $month, string $exportUrl, array $extra = []): Response
    {
        return Inertia::render('AdminAttendanceReportPage', array_merge([
            'mode' => $mode,
            'title' => $title,
            'subtitle' => $subtitle,
            'metrics' => $metrics,
            'columns' => $columns,
            'rows' => $rows,
            'emptyText' => $emptyText,
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'month' => $month,
                'label' => $from->format('d M Y').' - '.$to->format('d M Y'),
                'exportUrl' => $exportUrl,
            ],
        ], $extra));
    }

    private function resolvePeriod(Request $request): array
    {
        $month = (string) $request->query('month', '');

        if (preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            $from = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay();
            $to = $from->copy()->endOfMonth()->endOfDay();
            return [$from, $to, $from->format('Y-m')];
        }

        $from = $request->date('from')?->startOfDay() ?? now()->startOfMonth();
        $to = $request->date('to')?->endOfDay() ?? $from->copy()->endOfMonth()->endOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to, $from->format('Y-m')];
    }

    private function resolvePeriodUntilToday(Request $request): array
    {
        [$from, $to, $month] = $this->resolvePeriod($request);
        $today = now()->endOfDay();

        if ($to->gt($today)) {
            $to = $today;
        }

        if ($from->gt($to)) {
            $from = $to->copy()->startOfDay();
            $month = $from->format('Y-m');
        }

        return [$from, $to, $month];
    }

    private function downloadAttendanceWorkbook(string $title, CarbonInterface $from, CarbonInterface $to, array $records, string $filename, array $headings = ['No', 'Tanggal', 'Nama', 'Kelas', 'Check In', 'Check Out', 'Status']): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Absensi');

        foreach (['A1:G1', 'A2:G2', 'A3:G3', 'A4:G4'] as $range) {
            $sheet->mergeCells($range);
        }

        $sheet->setCellValue('A1', $title);
        $sheet->setCellValue('A2', 'Rhino Fighter');
        $sheet->setCellValue('A3', 'Periode: '.$from->format('d M Y').' - '.$to->format('d M Y'));
        $sheet->setCellValue('A4', 'Digenerate pada: '.now(config('app.timezone', 'Asia/Jakarta'))->format('d/m/Y H:i').' ('.config('app.timezone', 'Asia/Jakarta').')');
        $sheet->fromArray($headings, null, 'A6');

        if (count($records) > 0) {
            $sheet->fromArray($records, null, 'A7');
        }

        $lastRow = max(6 + count($records), 7);
        $sheet->getStyle('A1:G4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(18);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A3')->getFont()->setSize(12);
        $sheet->getStyle('A4')->getFont()->setItalic(true)->setSize(11);
        $sheet->getStyle('A6:G6')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '404040']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);
        $sheet->getStyle('A6:G'.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D9D9D9');
        $sheet->getStyle('A7:G'.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A7:B'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('E7:G'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A7:G'.$lastRow)->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getRowDimension(6)->setRowHeight(24);

        foreach (['A' => 8, 'B' => 26, 'C' => 18, 'D' => 18, 'E' => 18, 'F' => 16, 'G' => 20] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->freezePane('A7');

        $directory = storage_path('app/exports');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/'.$filename;
        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    private function exportFilename(string $prefix, CarbonInterface $from, CarbonInterface $to): string
    {
        $isFullMonth = $from->toDateString() === $from->copy()->startOfMonth()->toDateString()
            && $to->toDateString() === $from->copy()->endOfMonth()->toDateString();

        $period = $isFullMonth
            ? $from->format('Y-m')
            : $from->format('Y-m-d').'_'.$to->format('Y-m-d');

        return $prefix.'-'.$period.'.xlsx';
    }

    private function athleteStatusLabel(string $status): string
    {
        return match ($status) {
            'PRESENT' => 'Hadir',
            'LATE' => 'Terlambat',
            'EXCUSED' => 'Izin',
            'SICK' => 'Sakit',
            'ABSENT' => 'Alpha',
            default => $status ?: '-',
        };
    }

    private function coachStatusLabel(string $status): string
    {
        return match ($status) {
            'TEACH' => 'Mengajar',
            'NOT_TEACH' => 'Tidak Mengajar',
            default => $status ?: '-',
        };
    }

    private function attendanceRateStatus(int $rate, int $total): string
    {
        if ($total === 0) {
            return 'Belum Ada Data';
        }

        return match (true) {
            $rate >= 85 => 'Baik',
            $rate >= 70 => 'Perlu Pantau',
            default => 'Perlu Perhatian',
        };
    }

    private function sessionOptionLabel(TrainingSession $session): string
    {
        $date = Carbon::parse((string) $session->session_date)->format('Y-m-d');
        $start = $session->start_time ? Carbon::parse((string) $session->start_time)->format('H:i') : '--:--';
        $end = $session->end_time ? Carbon::parse((string) $session->end_time)->format('H:i') : '--:--';

        return $date.' '.$start.'-'.$end.' · '.$session->title;
    }
}
