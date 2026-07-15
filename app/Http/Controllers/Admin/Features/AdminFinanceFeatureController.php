<?php

namespace App\Http\Controllers\Admin\Features;

use App\Models\BillingSetting;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Response;

class AdminFinanceFeatureController extends BaseAdminFeatureController
{
    public function index(Request $request): Response
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
        $validated = $request->validate([
            'invoice_day' => ['required', 'integer', 'min:1', 'max:28'],
            'invoice_time' => ['required', 'date_format:H:i'],
            'default_amount' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);
        BillingSetting::monthlyTuition()->update([
            'invoice_day' => $validated['invoice_day'],
            'invoice_time' => $validated['invoice_time'].':00',
            'default_amount' => $validated['default_amount'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return redirect()->route('admin.payments')->with('status', 'Monthly tuition billing settings updated.');
    }

    public function generateMonthlyDues(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        Artisan::call('tuition:generate-monthly --force');

        return redirect()->route('admin.payments')->with('status', trim(Artisan::output()));
    }
}
