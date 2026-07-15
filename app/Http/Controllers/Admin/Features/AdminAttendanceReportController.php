<?php

namespace App\Http\Controllers\Admin\Features;

use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Coach;
use App\Models\CoachAttendance;
use App\Models\TrainingSession;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Response;

class AdminAttendanceReportController extends BaseAdminFeatureController
{
    public function athletes(Request $request): Response
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

    public function coaches(Request $request): Response
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
}
