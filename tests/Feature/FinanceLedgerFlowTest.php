<?php

use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function financeTestBillPayload(User $recipient, array $overrides = []): array
{
    return array_merge([
        'bill_kind' => 'INVOICE',
        'billable_user_id' => $recipient->id,
        'payment_type' => 'TUITION',
        'total_amount' => 300000,
        'payment_date' => '2026-07-01',
        'due_date' => '2026-07-15',
        'collection_method' => 'TRANSFER',
        'notes' => 'July tuition',
    ], $overrides);
}

it('issues distinct invoices instead of merging repeated bills', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $recipient = User::factory()->create(['role' => 'athlete']);

    $this->actingAs($admin)->post(route('payments.store'), financeTestBillPayload($recipient))->assertRedirect(route('payments.index'));
    $this->actingAs($admin)->post(route('payments.store'), financeTestBillPayload($recipient))->assertRedirect(route('payments.index'));

    $payments = Payment::query()->orderBy('payment_id')->get();

    expect($payments)->toHaveCount(2)
        ->and($payments->pluck('invoice_number')->filter()->unique())->toHaveCount(2)
        ->and($payments->every(fn (Payment $payment): bool => (float) $payment->paid_amount === 0.0))->toBeTrue()
        ->and($payments->every(fn (Payment $payment): bool => (float) $payment->remaining_amount === 300000.0))->toBeTrue();
});

it('records partial and final admin payments through the transaction ledger', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $recipient = User::factory()->create(['role' => 'athlete']);
    $payment = Payment::query()->create([
        'billable_user_id' => $recipient->id,
        'bill_kind' => 'INVOICE',
        'payment_type' => 'TUITION',
        'amount' => 300000,
        'total_amount' => 300000,
        'paid_amount' => 0,
        'remaining_amount' => 300000,
        'payment_date' => '2026-07-01',
        'due_date' => '2026-07-15',
        'collection_method' => 'CASH',
        'status' => 'PENDING',
        'proof_status' => 'NONE',
    ]);

    $this->actingAs($admin)->post(route('payments.transactions.store', $payment), [
        'amount' => 100000,
        'transaction_date' => '2026-07-10',
        'payment_method' => 'CASH',
        'notes' => 'First cash installment',
    ])->assertRedirect(route('payments.index'));

    $payment->refresh();
    expect((float) $payment->paid_amount)->toBe(100000.0)
        ->and((float) $payment->remaining_amount)->toBe(200000.0)
        ->and($payment->status)->toBe('PENDING');

    $this->assertDatabaseHas('payment_transactions', [
        'payment_id' => $payment->payment_id,
        'verified_by' => $admin->id,
        'amount' => 100000,
        'payment_method' => 'CASH',
        'transaction_type' => PaymentTransaction::TYPE_PAYMENT,
    ]);

    $this->actingAs($admin)->post(route('payments.transactions.store', $payment), [
        'amount' => 200000,
        'transaction_date' => '2026-07-11',
        'payment_method' => 'TRANSFER',
    ])->assertRedirect(route('payments.index'));

    $payment->refresh();
    expect((float) $payment->paid_amount)->toBe(300000.0)
        ->and((float) $payment->remaining_amount)->toBe(0.0)
        ->and($payment->status)->toBe('COMPLETED')
        ->and($payment->transactions()->where('transaction_type', PaymentTransaction::TYPE_PAYMENT)->count())->toBe(2);
});

it('does not allow bill edits to overwrite approved balances', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $recipient = User::factory()->create(['role' => 'athlete']);
    $payment = Payment::query()->create([
        'billable_user_id' => $recipient->id,
        'bill_kind' => 'INVOICE',
        'payment_type' => 'TUITION',
        'amount' => 300000,
        'total_amount' => 300000,
        'paid_amount' => 0,
        'remaining_amount' => 300000,
        'payment_date' => '2026-07-01',
        'due_date' => '2026-07-15',
        'collection_method' => 'TRANSFER',
        'status' => 'PENDING',
        'proof_status' => 'NONE',
    ]);

    $this->actingAs($admin)->post(route('payments.transactions.store', $payment), [
        'amount' => 125000,
        'transaction_date' => '2026-07-10',
        'payment_method' => 'TRANSFER',
    ])->assertRedirect(route('payments.index'));

    $this->actingAs($admin)->put(route('payments.update', $payment), financeTestBillPayload($recipient, [
        'total_amount' => 350000,
        'paid_amount' => 0,
        'notes' => 'Corrected bill total',
    ]))->assertRedirect(route('payments.index'));

    $payment->refresh();
    expect((float) $payment->total_amount)->toBe(350000.0)
        ->and((float) $payment->paid_amount)->toBe(125000.0)
        ->and((float) $payment->remaining_amount)->toBe(225000.0)
        ->and((float) $payment->transactions()->where('transaction_type', PaymentTransaction::TYPE_PAYMENT)->sum('amount'))->toBe(125000.0);
});

it('supports multiple receipt installments while preserving monetary history', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $recipient = User::factory()->create(['role' => 'athlete']);
    $payment = Payment::query()->create([
        'billable_user_id' => $recipient->id,
        'bill_kind' => 'INVOICE',
        'payment_type' => 'CHAMPIONSHIP',
        'amount' => 250000,
        'total_amount' => 250000,
        'paid_amount' => 0,
        'remaining_amount' => 250000,
        'payment_date' => now()->toDateString(),
        'due_date' => now()->addDays(14)->toDateString(),
        'collection_method' => 'TRANSFER',
        'status' => 'PENDING',
        'proof_status' => 'NONE',
    ]);

    foreach ([100000, 150000] as $index => $amount) {
        $this->actingAs($recipient)->post(route('payments.proof.submit', $payment), [
            'notes' => 'Installment '.($index + 1),
            'proof_file' => UploadedFile::fake()->image('receipt-'.$index.'.jpg'),
        ])->assertRedirect(route('payments.index'));

        $payment->refresh();
        expect($payment->proof_status)->toBe('SUBMITTED');

        $this->actingAs($admin)->put(route('payments.proof.review', $payment), [
            'decision' => 'APPROVED',
            'approved_amount' => $amount,
            'notes' => 'Verified installment '.($index + 1),
        ])->assertRedirect(route('payments.index'));

        $payment->refresh();
    }

    expect($payment->status)->toBe('COMPLETED')
        ->and((float) $payment->paid_amount)->toBe(250000.0)
        ->and((float) $payment->remaining_amount)->toBe(0.0)
        ->and($payment->transactions()->where('transaction_type', PaymentTransaction::TYPE_PAYMENT)->count())->toBe(2);
});

it('blocks forced completion and records refunds as ledger movements', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $recipient = User::factory()->create(['role' => 'athlete']);
    $payment = Payment::query()->create([
        'billable_user_id' => $recipient->id,
        'bill_kind' => 'INVOICE',
        'payment_type' => 'TUITION',
        'amount' => 200000,
        'total_amount' => 200000,
        'paid_amount' => 0,
        'remaining_amount' => 200000,
        'payment_date' => now(),
        'due_date' => now()->addDays(14),
        'collection_method' => 'TRANSFER',
        'status' => 'PENDING',
        'proof_status' => 'NONE',
    ]);

    $this->actingAs($admin)
        ->from(route('payments.index'))
        ->put(route('payments.status.update', $payment), ['status' => 'COMPLETED'])
        ->assertSessionHasErrors('status');

    expect($payment->refresh()->status)->toBe('PENDING');

    $this->actingAs($admin)->post(route('payments.transactions.store', $payment), [
        'amount' => 200000,
        'transaction_date' => now()->toDateString(),
        'payment_method' => 'TRANSFER',
    ])->assertRedirect(route('payments.index'));

    $this->actingAs($admin)->put(route('payments.status.update', $payment), [
        'status' => 'REFUNDED',
    ])->assertRedirect(route('payments.index'));

    $payment->refresh();
    expect($payment->status)->toBe('REFUNDED')
        ->and((float) $payment->paid_amount)->toBe(0.0)
        ->and((float) $payment->remaining_amount)->toBe(200000.0)
        ->and($payment->transactions()->where('transaction_type', 'REFUND')->count())->toBe(1)
        ->and($payment->transactions()->where('transaction_type', PaymentTransaction::TYPE_STATUS_CHANGE)->count())->toBe(1);

    $this->actingAs($admin)
        ->from(route('payments.index'))
        ->put(route('payments.status.update', $payment), ['status' => 'PENDING'])
        ->assertSessionHasErrors('status');
});

it('prioritizes receipt review and overdue bills for the admin', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $recipient = User::factory()->create(['role' => 'athlete']);

    $overdue = Payment::query()->create([
        'billable_user_id' => $recipient->id,
        'bill_kind' => 'INVOICE',
        'payment_type' => 'TUITION',
        'amount' => 200000,
        'total_amount' => 200000,
        'paid_amount' => 0,
        'remaining_amount' => 200000,
        'payment_date' => now()->subMonth(),
        'due_date' => now()->subDay(),
        'collection_method' => 'TRANSFER',
        'status' => 'PENDING',
        'proof_status' => 'NONE',
    ]);
    $review = Payment::query()->create([
        'billable_user_id' => $recipient->id,
        'bill_kind' => 'INVOICE',
        'payment_type' => 'TUITION',
        'amount' => 300000,
        'total_amount' => 300000,
        'paid_amount' => 0,
        'remaining_amount' => 300000,
        'payment_date' => now(),
        'due_date' => now()->addDays(14),
        'collection_method' => 'TRANSFER',
        'status' => 'PENDING',
        'proof_status' => 'SUBMITTED',
        'proof_path' => 'payment-proofs/review.jpg',
    ]);

    $this->actingAs($admin)
        ->get(route('payments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('PaymentsPage')
            ->where('rows.0.payment_id', $review->payment_id)
            ->where('rows.1.payment_id', $overdue->payment_id)
            ->where('financeAttention.proof_review_count', 1)
            ->where('financeAttention.overdue_count', 1));
});

it('exports invoice and ledger reconciliation columns', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $recipient = User::factory()->create(['role' => 'athlete']);
    Payment::query()->create([
        'billable_user_id' => $recipient->id,
        'bill_kind' => 'INVOICE',
        'payment_type' => 'TUITION',
        'amount' => 300000,
        'total_amount' => 300000,
        'paid_amount' => 0,
        'remaining_amount' => 300000,
        'payment_date' => '2026-07-01',
        'due_date' => '2026-07-15',
        'collection_method' => 'TRANSFER',
        'status' => 'PENDING',
        'proof_status' => 'NONE',
    ]);

    $response = $this->actingAs($admin)->get(route('payments.export.csv'));
    $response->assertOk();

    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toContain('invoice_number')
        ->and($csv)->toContain('due_date')
        ->and($csv)->toContain('ledger_paid_amount')
        ->and($csv)->toContain('ledger_consistent');
});

it('seeds finance balances that agree with transaction history', function () {
    $this->seed();

    $this->artisan('finance:audit')->assertExitCode(0);

    expect(Payment::query()->whereNull('invoice_number')->count())->toBe(0)
        ->and(Payment::query()->whereNull('due_date')->count())->toBe(0)
        ->and(Payment::query()->whereNull('collection_method')->count())->toBe(0);
});
