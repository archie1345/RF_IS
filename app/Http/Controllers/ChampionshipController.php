<?php

namespace App\Http\Controllers;

use App\Models\Coach;
use App\Models\Event;
use App\Models\EventCoachRegistration;
use App\Models\EventRegistration;
use App\Models\Payment;
use App\Models\UserAchievement;
use App\Services\EventAccessService;
use App\Support\ActivityLogger;
use App\Support\Domain\PaymentStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ChampionshipController extends Controller
{
    public function __construct(private readonly EventAccessService $eventAccess) {}

    public function show(Request $request, Event $event): Response
    {
        $user = $request->user();
        $event->load([
            'registrations.athlete.user:id,name,email',
            'coachRegistrations.coach.user:id,name,email',
        ]);

        $canManageEvent = $this->eventAccess->canManage($user, $event);
        $registrationOpen = $this->registrationIsOpen($event);
        $ownedAthleteIds = $this->ownedAthleteIds($user);
        $registrations = $event->registrations;

        // Athletes may see the public participant list, but only their own row is editable.
        if ($user?->isParent() && ! $canManageEvent) {
            $registrations = $registrations
                ->filter(fn (EventRegistration $registration): bool => $ownedAthleteIds->contains((string) $registration->athlete_id));
        } elseif (! $user?->isAthlete() && ! $canManageEvent) {
            $registrations = collect();
        }

        return Inertia::render('ChampionshipDetailPage', [
            'isAdmin' => $user?->isAdmin() ?? false,
            'isAthlete' => $user?->isAthlete() ?? false,
            'canManageCoaches' => $canManageEvent,
            'canRecordResult' => $canManageEvent,
            'canDeleteRegistration' => $canManageEvent,
            'event' => [
                'id' => $event->event_id,
                'name' => $event->e_name,
                'date' => Carbon::parse($event->e_date)->format('d M Y'),
                'location' => $event->location ?? '-',
                'gmaps_url' => $event->gmaps_url,
                'entry_fee' => (float) $event->entry_fee,
                'status' => $event->status,
                'registration_deadline' => $this->effectiveRegistrationDeadline($event)->format('d M Y H:i'),
                'registration_open' => $registrationOpen,
            ],
            'athleteRows' => $registrations->map(function (EventRegistration $registration) use ($canManageEvent, $ownedAthleteIds, $registrationOpen): array {
                $isOwned = $ownedAthleteIds->contains((string) $registration->athlete_id);

                return [
                    'id' => 'ATHREG-'.$registration->evrid,
                    'registration_id' => $registration->evrid,
                    'athlete_user_id' => $registration->athlete?->user?->id,
                    'athlete' => $registration->athlete?->user?->name ?? 'Unknown athlete',
                    'category' => $registration->category,
                    'classification' => $registration->classification ?? '-',
                    'class_name' => $registration->class_name ?? '-',
                    'division' => $registration->division ?? '-',
                    'team_contingent' => $registration->team_contingent ?? 'Rhino Fighter',
                    'status' => $registration->status,
                    'is_own_registration' => $isOwned,
                    'can_edit_registration' => $canManageEvent || ($registrationOpen && $isOwned),
                ];
            })->values(),
            'coachRows' => ($canManageEvent ? $event->coachRegistrations : collect())->map(
                fn (EventCoachRegistration $registration): array => [
                    'id' => 'COAREG-'.$registration->id,
                    'registration_id' => $registration->id,
                    'coach' => $registration->coach?->user?->name ?? 'Unknown coach',
                    'role' => $registration->role ?? '-',
                ],
            )->values(),
            'coachOptions' => ($user?->isAdmin()
                ? Coach::query()
                    ->where('status', 'active')
                    ->with('user:id,name')
                    ->get()
                : collect())
                ->map(fn (Coach $coach): array => [
                    'value' => $coach->coach_id,
                    'label' => $coach->user?->name ?? 'Unknown coach',
                ])
                ->values(),
        ]);
    }

    public function storeEvent(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $validated = $request->validate($this->eventRules());
        $this->ensureValidRegistrationDeadline($validated);
        $event = Event::query()->create($this->eventPayload($validated));
        ActivityLogger::log($request, 'event.created', 'event', 'Created championship event', $event);

        return redirect()->route('championships.index')->with('status', 'Kejuaraan berhasil dibuat.');
    }

    public function updateEvent(Request $request, Event $event): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $validated = $request->validate($this->eventRules());
        $this->ensureValidRegistrationDeadline($validated);
        $registrationCount = $event->registrations()->count();

        if ((int) ($validated['max_slots'] ?? 24) < $registrationCount) {
            return back()->withErrors([
                'max_slots' => 'Maximum athletes cannot be lower than the current registration count.',
            ]);
        }

        $event->update($this->eventPayload($validated));
        ActivityLogger::log($request, 'event.updated', 'event', 'Updated championship event', $event);

        return back()->with('status', 'Kejuaraan berhasil diperbarui.');
    }

    public function destroyEvent(Request $request, Event $event): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if ($event->registrations()->withTrashed()->exists()) {
            return back()->withErrors([
                'event' => 'Event with registration history cannot be deleted. Change its status to CANCELED instead.',
            ]);
        }

        DB::transaction(function () use ($event): void {
            $event->coachRegistrations()->delete();
            $event->delete();
        });

        ActivityLogger::log($request, 'event.deleted', 'event', 'Deleted championship event', $event);

        return redirect()->route('championships.index')->with('status', 'Kejuaraan berhasil dihapus.');
    }

    public function storeRegistration(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->isAdmin() || $user?->isParent() || $user?->isAthlete(), 403);
        $validated = $request->validate($this->registrationRules());

        if ($user->isAthlete() && (string) $user->athleteProfile?->athlete_id !== (string) $validated['athlete_id']) {
            return back()->withErrors(['athlete_id' => 'Athlete can only register own account.']);
        }

        if ($user->isParent()) {
            $childIds = $user->children()->pluck('athletes.athlete_id')->map(fn ($id) => (string) $id)->all();
            if (! in_array((string) $validated['athlete_id'], $childIds, true)) {
                return back()->withErrors(['athlete_id' => 'Parent can only register linked children.']);
            }
        }

        $registration = DB::transaction(function () use ($validated): EventRegistration {
            $event = Event::query()
                ->lockForUpdate()
                ->findOrFail($validated['event_id']);

            if (! $this->registrationIsOpen($event)) {
                throw ValidationException::withMessages([
                    'event_id' => 'Batas waktu pendaftaran kejuaraan sudah berakhir atau pendaftaran sudah ditutup.',
                ]);
            }

            $duplicateExists = EventRegistration::query()
                ->where('event_id', $event->event_id)
                ->where('athlete_id', $validated['athlete_id'])
                ->exists();

            if ($duplicateExists) {
                throw ValidationException::withMessages([
                    'athlete_id' => 'This athlete is already registered for the selected championship.',
                ]);
            }

            $registrationCount = EventRegistration::query()
                ->where('event_id', $event->event_id)
                ->lockForUpdate()
                ->count();

            if ($registrationCount >= (int) $event->max_slots) {
                throw ValidationException::withMessages([
                    'event_id' => 'This championship has reached its maximum slot capacity.',
                ]);
            }

            $registration = EventRegistration::query()->create([
                'athlete_id' => $validated['athlete_id'],
                'event_id' => $event->event_id,
                'category' => $validated['category'],
                'classification' => $validated['classification'] ?? null,
                'class_name' => $validated['class_name'] ?? null,
                'division' => $validated['division'] ?? null,
                'team_contingent' => $validated['team_contingent'] ?: 'Rhino Fighter',
                'status' => 'PENDING',
            ]);
            $registration->loadMissing('athlete');

            $entryFee = (float) $event->entry_fee;
            if ($entryFee > 0) {
                Payment::query()->create([
                    'athlete_id' => $registration->athlete_id,
                    'billable_user_id' => $registration->athlete?->id,
                    'bill_kind' => 'INVOICE',
                    'payment_type' => 'CHAMPIONSHIP',
                    'reference_id' => $registration->evrid,
                    'amount' => $entryFee,
                    'total_amount' => $entryFee,
                    'paid_amount' => 0,
                    'remaining_amount' => $entryFee,
                    'payment_date' => now()->toDateString(),
                    'collection_method' => 'TRANSFER',
                    'status' => PaymentStatus::PENDING,
                    'proof_status' => PaymentStatus::PROOF_NONE,
                    'notes' => 'Event registration #'.$registration->evrid,
                ]);
            }

            return $registration;
        });

        ActivityLogger::log($request, 'event.registration.created', 'event', 'Created event registration', $registration, [
            'event_id' => $registration->event_id,
            'athlete_id' => $registration->athlete_id,
        ]);

        return redirect()->route('championships.index')->with('status', 'Pendaftaran berhasil dibuat.');
    }

    public function updateRegistration(Request $request, EventRegistration $registration): RedirectResponse
    {
        $registration->loadMissing('event');
        abort_unless($registration->event, 404);

        $canManage = $this->eventAccess->canManage($request->user(), $registration->event);
        $ownsRegistration = $this->ownedAthleteIds($request->user())
            ->contains((string) $registration->athlete_id);

        abort_unless($canManage || $ownsRegistration, 403);

        if (! $canManage && ! $this->registrationIsOpen($registration->event)) {
            return back()->withErrors([
                'registration' => 'Data pendaftaran tidak dapat diubah setelah batas waktu yang ditetapkan admin.',
            ]);
        }

        $validated = $request->validate($this->registrationRules(false));
        $validated['team_contingent'] = $validated['team_contingent'] ?: 'Rhino Fighter';
        $registration->update($validated);
        ActivityLogger::log($request, 'event.registration.updated', 'event', 'Updated event registration', $registration);

        return back()->with('status', 'Entri peserta berhasil diperbarui.');
    }

    public function destroyRegistration(Request $request, EventRegistration $registration): RedirectResponse
    {
        $registration->loadMissing('event');
        abort_unless($registration->event && $this->eventAccess->canManage($request->user(), $registration->event), 403);

        $payment = Payment::query()
            ->where('payment_type', 'CHAMPIONSHIP')
            ->where('reference_id', $registration->evrid)
            ->withCount('transactions')
            ->first();

        if ($payment && (
            $payment->transactions_count > 0
            || (float) $payment->paid_amount > 0
            || filled($payment->proof_path)
            || ($payment->proof_status ?? PaymentStatus::PROOF_NONE) !== PaymentStatus::PROOF_NONE
        )) {
            return back()->withErrors([
                'registration' => 'A registration with financial or proof history cannot be deleted. Resolve the linked bill first.',
            ]);
        }

        DB::transaction(function () use ($registration, $payment): void {
            $payment?->delete();
            UserAchievement::query()->where('event_registration_id', $registration->evrid)->delete();
            $registration->delete();
        });

        ActivityLogger::log($request, 'event.registration.deleted', 'event', 'Deleted event registration', $registration);

        return back()->with('status', 'Entri peserta berhasil dihapus.');
    }

    public function storeCoachRegistration(Request $request, Event $event): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->isAdmin() || $user?->isCoach(), 403);

        if (! in_array($event->status, ['SCHEDULED', 'ONGOING'], true)) {
            return back()->withErrors(['coach_id' => 'Coaches cannot be assigned to a closed event.']);
        }

        $validated = $request->validate([
            'coach_id' => [
                'nullable',
                Rule::exists('coaches', 'coach_id')->where('status', 'active')->whereNull('deleted_at'),
            ],
            'role' => ['nullable', 'string', 'max:120'],
        ]);
        $coachId = $user->isAdmin()
            ? (string) ($validated['coach_id'] ?? '')
            : (string) ($user->coachProfile?->coach_id ?? '');

        if ($coachId === '') {
            return back()->withErrors(['coach_id' => 'Coach profile not found.']);
        }

        $coachRegistration = EventCoachRegistration::query()->updateOrCreate(
            ['event_id' => $event->event_id, 'coach_id' => $coachId],
            ['role' => $validated['role'] ?? null],
        );

        ActivityLogger::log(
            $request,
            'event.coach.assigned',
            'event',
            'Assigned coach to championship event',
            $event,
            ['coach_id' => $coachRegistration->coach_id],
        );

        return redirect()->route('championships.show', $event)->with('status', 'Pelatih berhasil ditambahkan.');
    }

    public function destroyCoachRegistration(Request $request, EventCoachRegistration $coachRegistration): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->isAdmin() || $user?->isCoach(), 403);

        if ($user->isCoach() && (string) $coachRegistration->coach_id !== (string) $user->coachProfile?->coach_id) {
            abort(403);
        }

        $coachRegistration->delete();

        return back()->with('status', 'Penugasan pelatih berhasil dihapus.');
    }

    public function recordResult(Request $request, EventRegistration $registration): RedirectResponse
    {
        $registration->loadMissing(['event', 'athlete.user']);
        abort_unless($registration->event && $this->eventAccess->canManage($request->user(), $registration->event), 403);

        if (! in_array($registration->event->status, ['ONGOING', 'COMPLETED'], true)) {
            return back()->withErrors([
                'result' => 'Results can only be recorded while the event is ongoing or completed.',
            ]);
        }

        $validated = $request->validate([
            'medal' => ['required', Rule::in(['GOLD', 'SILVER', 'BRONZE', 'NONE'])],
            'class_name' => ['nullable', 'string', 'max:120'],
            'division' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
        ]);

        DB::transaction(function () use ($registration, $validated): void {
            $registration->update([
                'result_medal' => $validated['medal'],
                'result_class_name' => $validated['class_name'] ?? null,
                'result_division' => $validated['division'] ?? null,
                'result_category' => $validated['category'] ?? null,
                'status' => 'CONFIRMED',
            ]);

            $athleteUser = $registration->athlete?->user;
            if (! $athleteUser) {
                return;
            }

            UserAchievement::query()->updateOrCreate(
                ['event_registration_id' => $registration->evrid],
                [
                    'user_id' => $athleteUser->id,
                    'event_id' => $registration->event_id,
                    'championship_name' => $registration->event?->e_name ?? 'Championship',
                    'medal' => $validated['medal'],
                    'location' => $registration->event?->location,
                    'event_date' => $registration->event?->e_date,
                    'class_name' => $validated['class_name'] ?? null,
                    'division' => $validated['division'] ?? null,
                    'category' => $validated['category'] ?? null,
                    'is_auto_recorded' => true,
                ],
            );
        });

        ActivityLogger::log(
            $request,
            'event.result.recorded',
            'event',
            'Recorded championship result',
            $registration,
            ['medal' => $validated['medal']],
        );

        return back()->with('status', 'Hasil pertandingan berhasil disimpan.');
    }

    private function eventRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'date' => ['required', 'date'],
            'registration_deadline' => ['nullable', 'date'],
            'location' => ['required', 'string', 'max:255'],
            'gmaps_url' => ['nullable', 'url', 'max:255'],
            'entry_fee' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'max_slots' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'level' => ['nullable', Rule::in(['LOCAL', 'REGIONAL', 'NATIONAL', 'INTERNATIONAL'])],
            'status' => ['nullable', Rule::in(['SCHEDULED', 'ONGOING', 'COMPLETED', 'CANCELED'])],
        ];
    }

    private function eventPayload(array $validated): array
    {
        $registrationDeadline = filled($validated['registration_deadline'] ?? null)
            ? Carbon::parse($validated['registration_deadline'])
            : Carbon::parse($validated['date'])->endOfDay();

        return [
            'e_name' => $validated['name'],
            'e_date' => $validated['date'],
            'registration_deadline' => $registrationDeadline,
            'location' => $validated['location'],
            'gmaps_url' => $validated['gmaps_url'] ?? null,
            'level' => $validated['level'] ?? 'LOCAL',
            'entry_fee' => $validated['entry_fee'],
            'max_slots' => $validated['max_slots'] ?? 24,
            'status' => $validated['status'] ?? 'SCHEDULED',
        ];
    }

    private function registrationRules(bool $includeAthleteEvent = true): array
    {
        return array_filter([
            'athlete_id' => $includeAthleteEvent
                ? ['required', Rule::exists('athletes', 'athlete_id')->whereNull('deleted_at')]
                : null,
            'event_id' => $includeAthleteEvent
                ? ['required', Rule::exists('events', 'event_id')->whereNull('deleted_at')]
                : null,
            'category' => ['required', Rule::in(['KYORUGI', 'POOMSAE', 'FREESTYLE', 'UNKNOWN'])],
            'classification' => ['nullable', 'string', 'max:120'],
            'class_name' => ['nullable', 'string', 'max:120'],
            'division' => ['nullable', 'string', 'max:120'],
            'team_contingent' => ['nullable', 'string', 'max:120'],
        ]);
    }

    private function effectiveRegistrationDeadline(Event $event): Carbon
    {
        return $event->registration_deadline
            ? Carbon::parse($event->registration_deadline)
            : Carbon::parse($event->e_date)->endOfDay();
    }

    private function registrationIsOpen(Event $event): bool
    {
        return $event->status === 'SCHEDULED'
            && now(config('app.timezone', 'Asia/Jakarta'))->lt($this->effectiveRegistrationDeadline($event));
    }

    private function ensureValidRegistrationDeadline(array $validated): void
    {
        if (blank($validated['registration_deadline'] ?? null)) {
            return;
        }

        $deadline = Carbon::parse($validated['registration_deadline']);
        $eventEnd = Carbon::parse($validated['date'])->endOfDay();

        if ($deadline->gt($eventEnd)) {
            throw ValidationException::withMessages([
                'registration_deadline' => 'Batas pendaftaran tidak boleh melewati tanggal kejuaraan.',
            ]);
        }
    }

    private function ownedAthleteIds($user): Collection
    {
        if (! $user) {
            return collect();
        }

        if ($user->isAthlete()) {
            return collect([$user->athleteProfile?->athlete_id])
                ->filter()
                ->map(fn ($id): string => (string) $id);
        }

        if ($user->isParent()) {
            return $user->children()
                ->pluck('athletes.athlete_id')
                ->map(fn ($id): string => (string) $id);
        }

        return collect();
    }
}
