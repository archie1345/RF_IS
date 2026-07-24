<?php

namespace App\Http\Controllers;

use App\Models\InvoiceTemplate;
use App\Models\Payment;
use App\Presenters\PaymentRowPresenter;
use App\Services\PaymentVisibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class QrisPaymentPageController extends Controller
{
    public function __construct(
        private readonly PaymentVisibilityService $paymentVisibility,
        private readonly PaymentRowPresenter $paymentRows,
    ) {}

    public function __invoke(Request $request): Response
    {
        $template = Schema::hasTable('invoice_templates')
            ? InvoiceTemplate::query()->first()
            : null;

        $outstandingPayments = $this->paymentVisibility
            ->visiblePaymentsQuery($request)
            ->where('bill_kind', 'INVOICE')
            ->where('remaining_amount', '>', 0)
            ->with([
                'athlete.user:id,name',
                'billableUser:id,name',
                'payeeUser:id,name',
                'transactions.verifier:id,name',
            ])
            ->orderBy('due_date')
            ->orderBy('payment_id')
            ->limit(50)
            ->get()
            ->map(function (Payment $payment): array {
                $row = $this->paymentRows->row($payment);

                return [
                    'payment_id' => $payment->payment_id,
                    'invoice_number' => $payment->invoice_number,
                    'recipient' => $row['athlete'] ?? 'Unknown recipient',
                    'category' => $row['type'] ?? $payment->payment_type,
                    'balance' => $row['balance'] ?? $payment->remaining_amount,
                    'due' => $row['due'] ?? optional($payment->due_date)->format('d M Y'),
                    'is_overdue' => $payment->isOverdue(),
                ];
            })
            ->values();

        return Inertia::render('QrisPaymentPage', [
            'isAdmin' => (bool) $request->user()?->isAdmin(),
            'qris' => [
                'enabled' => $template?->qris_enabled ?? true,
                'label' => $template?->qris_label ?? 'Pembayaran QRIS',
                'instructions' => $template?->qris_instructions
                    ?: 'Pindai QRIS, bayar sesuai sisa tagihan, lalu unggah bukti pembayaran untuk direview admin.',
                'imageUrl' => $template?->qris_image_url,
                'configured' => filled($template?->qris_image_url),
            ],
            'outstandingPayments' => $outstandingPayments,
        ]);
    }
}
