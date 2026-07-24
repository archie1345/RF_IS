<?php

use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake(Payment::PROOF_DISK_PRIVATE);
    Storage::fake(Payment::PROOF_DISK_PUBLIC);
});

function createPrivateProofPayment(User $recipient): Payment
{
    return Payment::query()->create([
        'billable_user_id' => $recipient->id,
        'bill_kind' => 'INVOICE',
        'payment_type' => 'OTHER',
        'amount' => 250000,
        'total_amount' => 250000,
        'paid_amount' => 0,
        'remaining_amount' => 250000,
        'payment_date' => today()->toDateString(),
        'due_date' => today()->addWeek()->toDateString(),
        'collection_method' => 'TRANSFER',
        'status' => 'PENDING',
        'proof_status' => 'NONE',
    ]);
}

it('stores submitted payment proofs on private storage and exposes authorized routes', function () {
    $recipient = User::factory()->create([
        'role' => 'athlete',
        'email_verified_at' => now(),
    ]);
    $payment = createPrivateProofPayment($recipient);

    $this->actingAs($recipient)
        ->post(route('payments.proof.submit', $payment), [
            'notes' => 'First installment',
            'proof_file' => UploadedFile::fake()->image('receipt.jpg'),
        ])
        ->assertRedirect(route('payments.index'));

    $payment->refresh();
    $transaction = PaymentTransaction::query()
        ->where('payment_id', $payment->payment_id)
        ->where('transaction_type', PaymentTransaction::TYPE_PROOF_SUBMITTED)
        ->sole();

    expect($payment->proofStorageDisk())->toBe(Payment::PROOF_DISK_PRIVATE)
        ->and($transaction->proofStorageDisk())->toBe(Payment::PROOF_DISK_PRIVATE)
        ->and($transaction->proof_path)->toBe($payment->proof_path);

    Storage::disk(Payment::PROOF_DISK_PRIVATE)->assertExists($payment->proof_path);
    Storage::disk(Payment::PROOF_DISK_PUBLIC)->assertMissing($payment->proof_path);

    $this->actingAs($recipient)
        ->get(route('payments.proof.download', $payment))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->actingAs($recipient)
        ->get(route('payments.transactions.proof.download', $transaction))
        ->assertOk();
});

it('denies unrelated accounts while allowing admins to inspect payment proofs', function () {
    $recipient = User::factory()->create([
        'role' => 'athlete',
        'email_verified_at' => now(),
    ]);
    $payment = createPrivateProofPayment($recipient);
    $path = 'payment-proofs/'.$payment->payment_id.'/receipt.jpg';
    Storage::disk(Payment::PROOF_DISK_PRIVATE)->put($path, 'receipt');
    $payment->update([
        'proof_path' => $path,
        'proof_disk' => Payment::PROOF_DISK_PRIVATE,
        'proof_status' => 'SUBMITTED',
    ]);

    $unrelated = User::factory()->create([
        'role' => 'athlete',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($unrelated)
        ->get(route('payments.proof.download', $payment))
        ->assertForbidden();

    $admin = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('payments.proof.download', $payment))
        ->assertOk();
});
