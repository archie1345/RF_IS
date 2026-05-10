<?php

use App\Models\Payment;
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
});
