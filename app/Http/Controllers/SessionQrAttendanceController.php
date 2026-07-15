<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Session;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SessionQrAttendanceController extends Controller
{
    public function generate(Request $request, Session $session): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->isAdmin() || $user?->isCoach(), 403);

        $validated = $request->validate([
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at'],
        ]);

        $sessionStart = $this->sessionBoundary($session, 'start_time');
        $sessionEnd = $this->sessionBoundary($session, 'end_time');
        $opensAt = Carbon::parse($validated['opens_at']);
        $closesAt = Carbon::parse($validated['closes_at']);

        if ($opensAt->lt($sessionStart)) {
            return back()->withErrors(['opens_at' => 'QR opening time cannot be before the session starts.'])->withInput();
        }

        if ($opensAt->gte($sessionEnd)) {
            return back()->withErrors(['opens_at' => 'QR opening time must be before the session ends.'])->withInput();
        }

        if ($closesAt->gt($sessionEnd)) {
            return back()->withErrors(['closes_at' => 'QR closing time cannot be after the session ends.'])->withInput();
        }

        $token = Str::random(56);

        $session->forceFill([
            'attendance_scan_token' => $token,
            'attendance_token_hash' => hash('sha256', $token),
            'attendance_opens_at' => $opensAt,
            'attendance_closes_at' => $closesAt,
            'attendance_qr_generated_at' => now(),
            'attendance_qr_revoked_at' => null,
        ])->save();

        return back()->with('status', 'Attendance QR generated. Athletes must scan it with a phone.');
    }

    public function revoke(Request $request, Session $session): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->isAdmin() || $user?->isCoach(), 403);

        $session->forceFill([
            'attendance_qr_revoked_at' => now(),
        ])->save();

        return back()->with('status', 'Attendance QR revoked.');
    }

    public function show(Request $request, string $token): Response
    {
        $session = $this->findSessionByToken($token);
        $deviceAllowed = $this->isPhone($request);

        return Inertia::render('AttendanceScanPage', $this->scanProps($request, $session, $token, $deviceAllowed));
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $session = $this->findSessionByToken($token);

        if (! $this->isPhone($request)) {
            return back()->withErrors(['device' => 'QR attendance is phone-only. Please scan the QR with a mobile phone.']);
        }

        if (! $session) {
            return back()->withErrors(['token' => 'This QR attendance link is invalid.']);
        }

        if ($session->attendance_qr_revoked_at) {
            return back()->withErrors(['token' => 'This QR attendance link has been revoked.']);
        }

        $now = now();
        if ($session->attendance_opens_at && $now->lt(Carbon::parse($session->attendance_opens_at))) {
            return back()->withErrors(['token' => 'Attendance is not open yet.']);
        }

        if ($session->attendance_closes_at && $now->gt(Carbon::parse($session->attendance_closes_at))) {
            return back()->withErrors(['token' => 'Attendance is already closed.']);
        }

        $user = $request->user();
        abort_unless($user?->isAthlete(), 403);

        $athlete = Athlete::query()->where('id', $user->id)->first();
        abort_unless($athlete, 403);

        abort_unless($this->athleteCanAttend($athlete, $session), 403);

        DB::transaction(function () use ($athlete, $session): void {
            $attendance = Attendance::withTrashed()
                ->where('athlete_id', $athlete->athlete_id)
                ->where('coach_session_id', $session->csid)
                ->lockForUpdate()
                ->first();

            if ($attendance?->trashed()) {
                $attendance->restore();
            }

            if (! $attendance) {
                $attendance = new Attendance([
                    'athlete_id' => $athlete->athlete_id,
                    'coach_session_id' => $session->csid,
                    'date' => $session->session_date,
                ]);
            }

            if ($attendance->status !== 'PRESENT') {
                $attendance->status = 'PRESENT';
                $attendance->checked_in_at = now();
                $attendance->date = $attendance->date ?: $session->session_date;
                $attendance->save();
            }
        });

        return redirect()->route('attendance.scan.show', $token)->with('status', 'Attendance recorded successfully.');
    }

    private function findSessionByToken(string $token): ?Session
    {
        return Session::query()
            ->with(['branch:branch_id,branch_name', 'group:group_id,group_name'])
            ->where('attendance_scan_token', $token)
            ->where('attendance_token_hash', hash('sha256', $token))
            ->first();
    }

    private function scanProps(Request $request, ?Session $session, string $token, bool $deviceAllowed): array
    {
        $user = $request->user();
        $athlete = $user?->isAthlete() ? Athlete::query()->where('id', $user->id)->first() : null;
        $attendance = null;
        $message = null;
        $canSubmit = false;
        $state = 'invalid';

        if (! $deviceAllowed) {
            $message = 'QR attendance is phone-only. Please scan this QR with a mobile phone.';
            $state = 'desktop_blocked';
        } elseif (! $session) {
            $message = 'This QR attendance link is invalid.';
        } elseif ($session->attendance_qr_revoked_at) {
            $message = 'This QR attendance link has been revoked.';
            $state = 'revoked';
        } elseif ($session->attendance_opens_at && now()->lt(Carbon::parse($session->attendance_opens_at))) {
            $message = 'Attendance is not open yet.';
            $state = 'not_open';
        } elseif ($session->attendance_closes_at && now()->gt(Carbon::parse($session->attendance_closes_at))) {
            $message = 'Attendance is already closed.';
            $state = 'closed';
        } elseif (! $user?->isAthlete()) {
            $message = 'Please log in with an athlete account to check in.';
            $state = 'athlete_required';
        } elseif (! $athlete || ! $this->athleteCanAttend($athlete, $session)) {
            $message = 'This athlete is not eligible for this session.';
            $state = 'not_eligible';
        } else {
            $attendance = Attendance::query()
                ->where('athlete_id', $athlete->athlete_id)
                ->where('coach_session_id', $session->csid)
                ->first();

            if ($attendance?->status === 'PRESENT') {
                $message = 'You are already checked in for this session.';
                $state = 'already_present';
            } else {
                $message = 'Confirm this session and tap check in.';
                $state = 'ready';
                $canSubmit = true;
            }
        }

        return [
            'token' => $token,
            'deviceAllowed' => $deviceAllowed,
            'state' => $state,
            'message' => $message,
            'canSubmit' => $canSubmit,
            'session' => $session ? [
                'title' => $session->title,
                'date' => Carbon::parse((string) $session->session_date)->format('Y-m-d'),
                'time' => Carbon::parse((string) $session->start_time)->format('H:i').' - '.Carbon::parse((string) $session->end_time)->format('H:i'),
                'location' => $session->location,
                'branch' => $session->branch?->branch_name,
                'group' => $session->group?->group_name ?? 'All groups',
                'opens_at' => $session->attendance_opens_at ? Carbon::parse($session->attendance_opens_at)->format('Y-m-d H:i') : null,
                'closes_at' => $session->attendance_closes_at ? Carbon::parse($session->attendance_closes_at)->format('Y-m-d H:i') : null,
            ] : null,
            'athlete' => $athlete ? [
                'name' => $athlete->user?->name ?? $request->user()?->name,
                'current_status' => $attendance?->status,
            ] : null,
        ];
    }

    private function athleteCanAttend(Athlete $athlete, Session $session): bool
    {
        return (int) $athlete->branch_id === (int) $session->branch_id
            && ($session->group_id === null || (int) $athlete->group_id === (int) $session->group_id);
    }

    private function sessionBoundary(Session $session, string $timeColumn): Carbon
    {
        return Carbon::parse(
            Carbon::parse((string) $session->session_date)->format('Y-m-d').' '.Carbon::parse((string) $session->{$timeColumn})->format('H:i:s')
        );
    }

    private function isPhone(Request $request): bool
    {
        $agent = strtolower((string) $request->userAgent());

        return str_contains($agent, 'iphone')
            || (str_contains($agent, 'android') && str_contains($agent, 'mobile'))
            || str_contains($agent, 'windows phone');
    }
}
