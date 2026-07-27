<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Domain\PaymentStatus;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminPayrollController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $currentMonth = now(config('app.timezone', 'Asia/Jakarta'))->startOfMonth();
        $coaches = User::query()
            ->withRole('coach')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
        $payrolls = Payment::query()
            ->where('bill_kind', 'PAYROLL')
            ->with(['payeeUser:id,name,email', 'transactions'])
            ->latest('payroll_period')
            ->latest('payment_id')
            ->get();
        $currentMonthPayrolls = $payrolls
            ->filter(fn (Payment $payment): bool => $this->paymentFallsInMonth($payment, $currentMonth))
            ->values();
        $paidCoachIds = $currentMonthPayrolls->pluck('payee_user_id')->filter()->unique();
        $missingCoachCount = $coaches->whereNotIn('id', $paidCoachIds)->count();

        return Inertia::render('admin/AdminPayrollPage', [
            'reminder' => [
                'needed' => $missingCoachCount > 0,
                'month' => $currentMonth->translatedFormat('F Y'),
                'count' => $paidCoachIds->count(),
                'expected' => $coaches->count(),
                'missing' => $missingCoachCount,
            ],
            'metrics' => [
                [
                    'label' => 'Payroll bulan ini',
                    'value' => $paidCoachIds->count().' / '.$coaches->count(),
                    'detail' => $missingCoachCount > 0
                        ? $missingCoachCount.' pelatih belum memiliki slip pembayaran'
                        : 'Semua pelatih sudah memiliki bukti pembayaran',
                    'tone' => $missingCoachCount > 0 ? 'danger' : 'success',
                ],
                [
                    'label' => 'Total dibayar bulan ini',
                    'value' => $this->rupiah((float) $currentMonthPayrolls->sum('paid_amount')),
                    'detail' => 'Termasuk bonus pelatih',
                    'tone' => 'info',
                ],
                [
                    'label' => 'Bonus bulan ini',
                    'value' => $this->rupiah((float) $currentMonthPayrolls->sum('payroll_bonus_amount')),
                    'detail' => 'Bonus yang tercatat di slip payroll',
                    'tone' => 'warning',
                ],
            ],
            'coaches' => $coaches
                ->map(fn (User $user): array => [
                    'value' => $user->id,
                    'label' => trim($user->name.' - '.$user->email),
                ])
                ->values(),
            'rows' => $payrolls->map(fn (Payment $payment): array => [
                'id' => 'PAY-'.$payment->payment_id,
                'payment_id' => $payment->payment_id,
                'invoice_number' => $payment->invoice_number,
                'coach' => $payment->payeeUser?->name ?? 'Pelatih tidak dikenal',
                'period' => $payment->payroll_period?->translatedFormat('F Y')
                    ?? $payment->payment_date?->translatedFormat('F Y')
                    ?? '-',
                'basis' => $this->basisLabel((string) $payment->payroll_basis_type),
                'units' => $payment->payroll_units === null ? '-' : (string) $payment->payroll_units,
                'rate' => $this->rupiah((float) ($payment->payroll_rate ?? 0)),
                'base' => $this->rupiah((float) ($payment->payroll_base_amount ?? 0)),
                'bonus' => $this->rupiah((float) ($payment->payroll_bonus_amount ?? 0)),
                'total' => $this->rupiah((float) ($payment->total_amount ?? 0)),
                'paid_at' => $payment->payment_date?->format('d M Y') ?? '-',
                'status' => 'PAID',
                'receipt_url' => route('payments.export', $payment),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'coach_user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'payroll_period' => ['required', 'date_format:Y-m'],
            'basis_type' => ['required', Rule::in(['SESSION', 'HOUR', 'MONTH', 'FIXED', 'CUSTOM'])],
            'units' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'rate' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'base_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'bonus_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'paid_at' => ['required', 'date'],
            'payment_method' => ['required', Rule::in(['CASH', 'CARD', 'TRANSFER', 'OTHER'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $coach = User::query()->findOrFail($validated['coach_user_id']);
        if (! $coach->hasRole('coach')) {
            throw ValidationException::withMessages(['coach_user_id' => 'Akun yang dipilih bukan pelatih.']);
        }

        $period = Carbon::createFromFormat('Y-m', $validated['payroll_period'])->startOfMonth();
        if (Payment::query()
            ->where('bill_kind', 'PAYROLL')
            ->where('payee_user_id', $coach->id)
            ->whereDate('payroll_period', $period->toDateString())
            ->exists()) {
            throw ValidationException::withMessages([
                'payroll_period' => 'Slip payroll pelatih ini untuk periode tersebut sudah ada.',
            ]);
        }

        $basisType = $validated['basis_type'];
        $units = (float) ($validated['units'] ?? 0);
        $rate = (float) ($validated['rate'] ?? 0);
        $baseAmount = in_array($basisType, ['FIXED', 'CUSTOM'], true)
            ? (float) ($validated['base_amount'] ?? 0)
            : $units * $rate;
        $bonusAmount = (float) ($validated['bonus_amount'] ?? 0);
        $totalAmount = $baseAmount + $bonusAmount;

        if ($totalAmount <= 0) {
            throw ValidationException::withMessages([
                'base_amount' => 'Total payroll harus lebih dari nol.',
            ]);
        }

        $payment = DB::transaction(function () use ($request, $validated, $coach, $period, $basisType, $units, $rate, $baseAmount, $bonusAmount, $totalAmount): Payment {
            $paidAt = Carbon::parse($validated['paid_at']);

            $payment = Payment::query()->create([
                'payee_user_id' => $coach->id,
                'bill_kind' => 'PAYROLL',
                'payment_type' => 'OTHER',
                'payroll_period' => $period->toDateString(),
                'payroll_basis_type' => $basisType,
                'payroll_units' => $units,
                'payroll_rate' => $rate,
                'payroll_base_amount' => $baseAmount,
                'payroll_bonus_amount' => $bonusAmount,
                'amount' => $totalAmount,
                'total_amount' => $totalAmount,
                'paid_amount' => $totalAmount,
                'remaining_amount' => 0,
                'payment_date' => $paidAt->toDateString(),
                'due_date' => $paidAt->toDateString(),
                'collection_method' => $validated['payment_method'],
                'status' => PaymentStatus::COMPLETED,
                'proof_status' => PaymentStatus::PROOF_APPROVED,
                'notes' => trim((string) ($validated['notes'] ?? '')) ?: 'Payroll '.$period->translatedFormat('F Y'),
            ]);

            PaymentTransaction::query()->create([
                'payment_id' => $payment->payment_id,
                'verified_by' => $request->user()?->id,
                'amount' => $totalAmount,
                'transaction_date' => $paidAt,
                'payment_method' => $validated['payment_method'],
                'transaction_type' => PaymentTransaction::TYPE_PAYMENT,
                'notes' => 'Payroll marked paid when the receipt was issued.',
            ]);

            return $payment;
        });

        ActivityLogger::log($request, 'payroll.paid_receipt.created', 'finance', 'Created paid coach payroll receipt', $payment, [
            'coach_user_id' => $coach->id,
            'period' => $validated['payroll_period'],
            'base_amount' => $baseAmount,
            'bonus_amount' => $bonusAmount,
            'total_amount' => $totalAmount,
        ]);

        return back()->with('status', 'Slip payroll dan bukti pembayaran berhasil dibuat.');
    }

    private function paymentFallsInMonth(Payment $payment, CarbonInterface $month): bool
    {
        $date = $payment->payroll_period ?? $payment->payment_date;

        return $date?->isSameMonth($month) ?? false;
    }

    private function basisLabel(string $basis): string
    {
        return match ($basis) {
            'SESSION' => 'Per sesi',
            'HOUR' => 'Per jam',
            'MONTH' => 'Per bulan',
            'FIXED' => 'Nominal tetap',
            default => 'Kustom',
        };
    }

    private function rupiah(float $value): string
    {
        return 'Rp'.number_format($value, 0, ',', '.');
    }
}
