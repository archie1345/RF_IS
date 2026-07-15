<?php

use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('admin can post a targeted announcement and still see it in the announcement list', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('announcements.store'), [
            'title' => 'Training schedule update',
            'message' => 'Saturday training starts at 08:00.',
            'target_role' => 'ATHLETE',
        ])
        ->assertRedirect(route('announcements.index'));

    $this->assertDatabaseHas('announcements', [
        'title' => 'Training schedule update',
        'target_role' => 'ATHLETE',
    ]);

    $this->actingAs($admin)
        ->get(route('announcements.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AnnouncementsPage')
            ->has('rows', 1)
            ->where('rows.0.title', 'Training schedule update')
            ->where('rows.0.target', 'Athletes'));
});

test('admin issues a bill, user uploads proof, and admin approves it', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $member = User::factory()->create(['role' => 'athlete']);

    $this->actingAs($admin)
        ->post(route('payments.store'), [
            'bill_kind' => 'INVOICE',
            'billable_user_id' => $member->id,
            'payment_type' => 'TUITION',
            'total_amount' => 250000,
            'collection_method' => 'TRANSFER',
            'notes' => 'May tuition',
        ])
        ->assertRedirect(route('payments.index'));

    $payment = Payment::query()->firstOrFail();

    expect((float) $payment->paid_amount)->toBe(0.0)
        ->and((float) $payment->remaining_amount)->toBe(250000.0)
        ->and($payment->billable_user_id)->toBe($member->id);

    $this->actingAs($member)
        ->post(route('payments.proof.submit', $payment), [
            'notes' => 'Paid by bank transfer',
            'proof_file' => UploadedFile::fake()->image('receipt.jpg'),
        ])
        ->assertRedirect(route('payments.index'));

    $payment->refresh();
    expect($payment->proof_status)->toBe('SUBMITTED');
    Storage::disk('public')->assertExists($payment->proof_path);

    $this->actingAs($admin)
        ->put(route('payments.proof.review', $payment), [
            'decision' => 'APPROVED',
            'notes' => 'Receipt verified',
        ])
        ->assertRedirect(route('payments.index'));

    $payment->refresh();
    expect($payment->proof_status)->toBe('APPROVED')
        ->and($payment->status)->toBe('COMPLETED')
        ->and((float) $payment->paid_amount)->toBe(250000.0)
        ->and((float) $payment->remaining_amount)->toBe(0.0);

    $this->assertDatabaseHas('payment_transactions', [
        'payment_id' => $payment->payment_id,
        'verified_by' => $admin->id,
        'transaction_type' => PaymentTransaction::TYPE_PAYMENT,
        'payment_method' => 'TRANSFER',
    ]);
});

test('admin can partially approve proof and keep receipt history for the next upload', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $member = User::factory()->create(['role' => 'athlete']);

    $this->actingAs($admin)
        ->post(route('payments.store'), [
            'bill_kind' => 'INVOICE',
            'billable_user_id' => $member->id,
            'payment_type' => 'TUITION',
            'total_amount' => 250000,
            'collection_method' => 'TRANSFER',
            'notes' => 'June tuition',
        ])
        ->assertRedirect(route('payments.index'));

    $payment = Payment::query()->firstOrFail();

    $this->actingAs($member)
        ->post(route('payments.proof.submit', $payment), [
            'notes' => 'First installment transfer',
            'proof_file' => UploadedFile::fake()->image('first-receipt.jpg'),
        ])
        ->assertRedirect(route('payments.index'));

    $payment->refresh();
    $firstProofPath = $payment->proof_path;

    $this->actingAs($admin)
        ->put(route('payments.proof.review', $payment), [
            'decision' => 'APPROVED',
            'approved_amount' => 100000,
            'notes' => 'First installment verified',
        ])
        ->assertRedirect(route('payments.index'));

    $payment->refresh();
    expect($payment->proof_status)->toBe('NONE')
        ->and($payment->proof_path)->toBeNull()
        ->and($payment->status)->toBe('PENDING')
        ->and((float) $payment->paid_amount)->toBe(100000.0)
        ->and((float) $payment->remaining_amount)->toBe(150000.0);

    $transaction = PaymentTransaction::query()->firstOrFail();
    expect((float) $transaction->amount)->toBe(100000.0)
        ->and($transaction->proof_path)->toBe($firstProofPath)
        ->and($transaction->proof_notes)->toBe('First installment transfer');
    $this->assertStringContainsString('Proof approved: First installment verified', $transaction->notes);
    $this->assertStringContainsString('Submitted note: First installment transfer', $transaction->notes);
    Storage::disk('public')->assertExists($firstProofPath);

    $this->actingAs($member)
        ->get(route('payments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('PaymentsPage')
            ->where('rows.0.payment_id', $payment->payment_id)
            ->where('rows.0.proof_status', 'NONE')
            ->where('rows.0.proof_url', null)
            ->has('rows.0.transaction_history', 1)
            ->where('rows.0.transaction_history.0.proof_url', Storage::url($firstProofPath)));

    $this->actingAs($member)
        ->post(route('payments.proof.submit', $payment), [
            'notes' => 'Second installment transfer',
            'proof_file' => UploadedFile::fake()->image('second-receipt.jpg'),
        ])
        ->assertRedirect(route('payments.index'));

    $payment->refresh();
    expect($payment->proof_status)->toBe('SUBMITTED')
        ->and($payment->proof_path)->not->toBe($firstProofPath);
});

test('admin can change the person receiving an invoice', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $firstMember = User::factory()->create(['role' => 'athlete']);
    $secondMember = User::factory()->create(['role' => 'coach']);

    $this->actingAs($admin)
        ->post(route('payments.store'), [
            'bill_kind' => 'INVOICE',
            'billable_user_id' => $firstMember->id,
            'payment_type' => 'TUITION',
            'total_amount' => 250000,
            'collection_method' => 'TRANSFER',
            'notes' => 'May tuition',
        ])
        ->assertRedirect(route('payments.index'));

    $payment = Payment::query()->firstOrFail();

    $this->actingAs($admin)
        ->put(route('payments.update', $payment), [
            'bill_kind' => 'INVOICE',
            'billable_user_id' => $secondMember->id,
            'payment_type' => 'TUITION',
            'total_amount' => 250000,
            'paid_amount' => 0,
            'payment_date' => now()->toDateString(),
            'collection_method' => 'TRANSFER',
            'notes' => 'May tuition reassigned',
        ])
        ->assertRedirect(route('payments.index'));

    $payment->refresh();
    expect($payment->billable_user_id)->toBe($secondMember->id)
        ->and($payment->athlete_id)->toBeNull();
});

test('payment proof review rejects zero negative and over approval amounts without changing balance', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $member = User::factory()->create(['role' => 'athlete']);
    $payment = Payment::create([
        'bill_kind' => 'INVOICE',
        'billable_user_id' => $member->id,
        'payment_type' => 'TUITION',
        'amount' => 100000,
        'total_amount' => 100000,
        'paid_amount' => 0,
        'remaining_amount' => 100000,
        'payment_date' => now(),
        'status' => 'PENDING',
    ]);

    $submitProof = function () use ($member, $payment) {
        $this->actingAs($member)
            ->post(route('payments.proof.submit', $payment), [
                'notes' => 'Installment receipt',
                'proof_file' => UploadedFile::fake()->image('receipt.jpg'),
            ])
            ->assertRedirect(route('payments.index'));

        $payment->refresh();
    };

    foreach ([0, -1000, 100001] as $invalidAmount) {
        $submitProof();

        $this->actingAs($admin)
            ->from(route('payments.index'))
            ->put(route('payments.proof.review', $payment), [
                'decision' => 'APPROVED',
                'approved_amount' => $invalidAmount,
                'notes' => 'Invalid approval',
            ])
            ->assertSessionHasErrors('approved_amount');

        $payment->refresh();
        expect((float) $payment->paid_amount)->toBe(0.0)
            ->and((float) $payment->remaining_amount)->toBe(100000.0)
            ->and(PaymentTransaction::query()->count())->toBe(0);

        $payment->update([
            'proof_status' => 'NONE',
            'proof_path' => null,
            'proof_notes' => null,
        ]);
    }
});

test('second partial payment approval can complete the bill', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $member = User::factory()->create(['role' => 'athlete']);
    $payment = Payment::create([
        'bill_kind' => 'INVOICE',
        'billable_user_id' => $member->id,
        'payment_type' => 'TUITION',
        'amount' => 100000,
        'total_amount' => 100000,
        'paid_amount' => 0,
        'remaining_amount' => 100000,
        'payment_date' => now(),
        'status' => 'PENDING',
    ]);

    foreach ([50000, 50000] as $amount) {
        $this->actingAs($member)
            ->post(route('payments.proof.submit', $payment), [
                'notes' => 'Installment receipt',
                'proof_file' => UploadedFile::fake()->image('receipt.jpg'),
            ])
            ->assertRedirect(route('payments.index'));

        $this->actingAs($admin)
            ->put(route('payments.proof.review', $payment), [
                'decision' => 'APPROVED',
                'approved_amount' => $amount,
                'notes' => 'Approved installment',
            ])
            ->assertRedirect(route('payments.index'));

        $payment->refresh();
    }

    expect($payment->status)->toBe('COMPLETED')
        ->and($payment->proof_status)->toBe('APPROVED')
        ->and((float) $payment->paid_amount)->toBe(100000.0)
        ->and((float) $payment->remaining_amount)->toBe(0.0)
        ->and(PaymentTransaction::query()->where('payment_id', $payment->payment_id)->count())->toBe(2);
});

test('rejected payment proof does not increase paid amount or create transaction', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin']);
    $member = User::factory()->create(['role' => 'athlete']);
    $payment = Payment::create([
        'bill_kind' => 'INVOICE',
        'billable_user_id' => $member->id,
        'payment_type' => 'TUITION',
        'amount' => 100000,
        'total_amount' => 100000,
        'paid_amount' => 0,
        'remaining_amount' => 100000,
        'payment_date' => now(),
        'status' => 'PENDING',
    ]);

    $this->actingAs($member)
        ->post(route('payments.proof.submit', $payment), [
            'notes' => 'Unclear receipt',
            'proof_file' => UploadedFile::fake()->image('unclear-receipt.jpg'),
        ])
        ->assertRedirect(route('payments.index'));

    $this->actingAs($admin)
        ->put(route('payments.proof.review', $payment), [
            'decision' => 'REJECTED',
            'notes' => 'Amount does not match this bill',
        ])
        ->assertRedirect(route('payments.index'));

    $payment->refresh();

    expect($payment->proof_status)->toBe('REJECTED')
        ->and((float) $payment->paid_amount)->toBe(0.0)
        ->and((float) $payment->remaining_amount)->toBe(100000.0)
        ->and(PaymentTransaction::query()->where('payment_id', $payment->payment_id)->count())->toBe(0);
});
