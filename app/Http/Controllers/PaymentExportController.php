<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Presenters\PaymentRowPresenter;
use App\Support\ActivityLogger;
use App\Support\CsvCell;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentExportController extends Controller
{
    public function __construct(private readonly PaymentRowPresenter $paymentRows) {}

    public function __invoke(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $payments = Payment::query()
            ->with([
                'athlete.user:id,name,email,phone',
                'billableUser:id,name,email,phone',
                'payeeUser:id,name,email,phone',
                'transactions',
            ])
            ->orderBy('payment_date')
            ->orderBy('payment_id')
            ->get();

        ActivityLogger::log(
            $request,
            'payment.ledger.exported',
            'payment',
            'Exported finance ledger CSV',
            null,
            ['payment_count' => $payments->count()],
        );

        return response()->streamDownload(function () use ($payments): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'invoice_number',
                'bill_kind',
                'recipient',
                'payment_type',
                'issue_date',
                'due_date',
                'collection_method',
                'total_amount',
                'paid_amount',
                'remaining_amount',
                'ledger_paid_amount',
                'ledger_consistent',
                'status',
                'proof_status',
                'transaction_count',
                'notes',
            ]);

            foreach ($payments as $payment) {
                $ledgerPayments = (float) $payment->transactions
                    ->where('transaction_type', PaymentTransaction::TYPE_PAYMENT)
                    ->sum('amount');
                $ledgerRefunds = (float) $payment->transactions
                    ->where('transaction_type', PaymentTransaction::TYPE_REFUND)
                    ->sum('amount');
                $ledgerPaid = max($ledgerPayments - $ledgerRefunds, 0);
                $storedPaid = (float) ($payment->paid_amount ?? 0);

                fputcsv($handle, CsvCell::row([
                    $payment->invoice_number,
                    $payment->bill_kind,
                    $this->paymentRows->subject($payment),
                    $payment->payment_type,
                    optional($payment->payment_date)->format('Y-m-d'),
                    optional($payment->due_date)->format('Y-m-d'),
                    $payment->collection_method,
                    $payment->total_amount ?? $payment->amount,
                    $payment->paid_amount,
                    $payment->remaining_amount,
                    $ledgerPaid,
                    abs($ledgerPaid - $storedPaid) < 0.01 ? 'YES' : 'NO',
                    $payment->status,
                    $payment->proof_status,
                    $payment->transactions->count(),
                    $payment->notes,
                ]));
            }

            fclose($handle);
        }, 'finance_ledger_'.now()->format('Ymd_His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
