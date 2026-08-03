<?php

use App\Models\MessageTemplate;
use App\Models\Payment;
use App\Models\User;
use App\Presenters\PaymentRowPresenter;
use App\Services\PaymentReminderTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('allows admins to manage the WhatsApp payment reminder template', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $athlete = User::factory()->create(['role' => 'athlete']);

    $this->actingAs($admin)
        ->get(route('admin.whatsapp-template.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/WhatsAppTemplatePage')
            ->where('template.body', PaymentReminderTemplate::DEFAULT_BODY)
            ->where('contactNumber', '')
            ->has('placeholders', count(PaymentReminderTemplate::PLACEHOLDERS)));

    $this->actingAs($admin)
        ->put(route('admin.whatsapp-template.update'), [
            'body' => 'Halo {name}, invoice {invoice_number} memiliki sisa {remaining_amount}. Buka {payment_url}.',
            'contact_number' => '081234567890',
        ])
        ->assertRedirect();

    expect(MessageTemplate::query()->where('key', PaymentReminderTemplate::KEY)->value('body'))
        ->toBe('Halo {name}, invoice {invoice_number} memiliki sisa {remaining_amount}. Buka {payment_url}.');
    expect(MessageTemplate::query()->where('key', 'public_admin_whatsapp')->value('body'))
        ->toBe('081234567890');

    $this->actingAs($admin)
        ->put(route('admin.whatsapp-template.update'), [
            'body' => 'Halo {unknown_placeholder}',
        ])
        ->assertSessionHasErrors('body');

    $this->actingAs($athlete)
        ->get(route('admin.whatsapp-template.edit'))
        ->assertForbidden();
});

it('uses the configured template when an admin payment row builds a WhatsApp reminder link', function () {
    $member = User::factory()->create([
        'name' => 'Ayu Pratama',
        'role' => 'athlete',
        'phone' => '081234567890',
    ]);
    MessageTemplate::query()->create([
        'key' => PaymentReminderTemplate::KEY,
        'body' => 'Halo {name}. Sisa {remaining_amount}. Nomor {invoice_number}. {payment_url}',
    ]);
    $payment = Payment::query()->create([
        'billable_user_id' => $member->id,
        'bill_kind' => 'INVOICE',
        'payment_type' => 'TUITION',
        'amount' => 150000,
        'total_amount' => 150000,
        'paid_amount' => 0,
        'remaining_amount' => 150000,
        'payment_date' => '2026-07-25',
        'due_date' => '2026-08-08',
        'status' => 'PENDING',
        'proof_status' => 'NONE',
    ]);

    $presenter = app(PaymentRowPresenter::class);
    $payment->load(['billableUser', 'transactions']);
    $memberRow = $presenter->row($payment);
    $adminRow = $presenter->row($payment, true);
    parse_str((string) parse_url((string) $adminRow['whatsapp_url'], PHP_URL_QUERY), $query);

    expect($memberRow['whatsapp_url'])->toBeNull()
        ->and($adminRow['whatsapp_url'])->toStartWith('https://wa.me/6281234567890?text=')
        ->and($query['text'] ?? null)->toContain('Halo Ayu Pratama.')
        ->and($query['text'] ?? null)->toContain('Sisa Rp 150.000.')
        ->and($query['text'] ?? null)->toContain((string) $payment->invoice_number)
        ->and($query['text'] ?? null)->toContain(route('payments.index'));
});
