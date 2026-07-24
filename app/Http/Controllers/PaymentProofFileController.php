<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Services\PaymentVisibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofFileController extends Controller
{
    public function __construct(private readonly PaymentVisibilityService $paymentVisibility) {}

    public function payment(Request $request, Payment $payment): StreamedResponse
    {
        abort_unless($this->paymentVisibility->userCanViewPayment($request, $payment), 403);

        return $this->respond(
            $payment->proofStorageDisk(),
            $payment->proof_path,
            'payment-proof-'.$payment->payment_id,
        );
    }

    public function transaction(Request $request, PaymentTransaction $paymentTransaction): StreamedResponse
    {
        $paymentTransaction->loadMissing('payment');
        $payment = $paymentTransaction->payment;

        abort_unless($payment instanceof Payment, 404);
        abort_unless($this->paymentVisibility->userCanViewPayment($request, $payment), 403);

        return $this->respond(
            $paymentTransaction->proofStorageDisk(),
            $paymentTransaction->proof_path,
            'payment-proof-transaction-'.$paymentTransaction->ptid,
        );
    }

    private function respond(string $disk, ?string $path, string $fallbackName): StreamedResponse
    {
        abort_unless(filled($path) && Storage::disk($disk)->exists($path), 404);

        $extension = pathinfo((string) $path, PATHINFO_EXTENSION);
        $filename = $fallbackName.($extension !== '' ? '.'.$extension : '');
        $mimeType = Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream';

        return Storage::disk($disk)->response($path, $filename, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
