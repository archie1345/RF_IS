<?php

use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\PaymentReminderTemplate;
use Inertia\Testing\AssertableInertia as Assert;

test('admin can configure the WhatsApp number used by public signup', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->put(route('admin.whatsapp-template.update'), [
            'body' => PaymentReminderTemplate::DEFAULT_BODY,
            'contact_number' => '0812 3456 7890',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('message_templates', [
        'key' => 'public_admin_whatsapp',
        'body' => '0812 3456 7890',
    ]);

    auth()->logout();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('publicAdminWhatsapp', '0812 3456 7890'));
});

test('non admin cannot change the public WhatsApp contact', function () {
    $athlete = User::factory()->create(['role' => 'athlete']);

    $this->actingAs($athlete)
        ->put(route('admin.whatsapp-template.update'), [
            'body' => PaymentReminderTemplate::DEFAULT_BODY,
            'contact_number' => '081111111111',
        ])
        ->assertForbidden();

    expect(MessageTemplate::query()->where('key', 'public_admin_whatsapp')->exists())->toBeFalse();
});
