<?php

use App\Models\Payment;
use App\Models\User;
use App\Services\ActiveRoleContextService;
use Inertia\Testing\AssertableInertia as Assert;

function createWhatsAppReminderPayment(User $recipient): Payment
{
    return Payment::create([
        'bill_kind' => 'INVOICE',
        'billable_user_id' => $recipient->id,
        'payment_type' => 'TUITION',
        'amount' => 250000,
        'total_amount' => 250000,
        'paid_amount' => 0,
        'remaining_amount' => 250000,
        'payment_date' => now(),
        'due_date' => now()->addDays(7),
        'status' => 'PENDING',
    ]);
}

test('only an active admin receives WhatsApp reminder links in payment rows', function () {
    $user = User::factory()->create([
        'role' => 'admin',
        'phone' => '081234567890',
    ]);
    $user->roleAssignments()->createMany([
        ['role' => 'admin'],
        ['role' => 'athlete'],
    ]);
    $payment = createWhatsAppReminderPayment($user);

    $this->actingAs($user)
        ->withSession([ActiveRoleContextService::SESSION_KEY => 'athlete'])
        ->get(route('payments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('PaymentsPage')
            ->where('isAdmin', false)
            ->where('rows.0.payment_id', $payment->payment_id)
            ->where('rows.0.whatsapp_url', null));

    $this->actingAs($user)
        ->withSession([ActiveRoleContextService::SESSION_KEY => 'admin'])
        ->get(route('payments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('PaymentsPage')
            ->where('isAdmin', true)
            ->where('rows.0.payment_id', $payment->payment_id)
            ->where(
                'rows.0.whatsapp_url',
                fn (mixed $value): bool => is_string($value) && str_starts_with($value, 'https://wa.me/6281234567890?text='),
            ));
});
