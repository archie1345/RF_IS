<?php

use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\PaymentReminderTemplate;
use App\Services\PublicContactSettings;
use Inertia\Testing\AssertableInertia as Assert;

test('admin can configure the WhatsApp number and landing bubble used by public pages', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->put(route('admin.whatsapp-template.update'), [
            'body' => PaymentReminderTemplate::DEFAULT_BODY,
            'contact_number' => '0812 3456 7890',
            'bubble_enabled' => false,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('message_templates', [
        'key' => PublicContactSettings::CONTACT_KEY,
        'body' => '0812 3456 7890',
    ]);
    $this->assertDatabaseHas('message_templates', [
        'key' => PublicContactSettings::BUBBLE_ENABLED_KEY,
        'body' => '0',
    ]);

    $this->get(route('admin.whatsapp-template.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/WhatsAppTemplatePage')
            ->where('contactNumber', '0812 3456 7890')
            ->where('bubbleEnabled', false));

    auth()->logout();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('publicAdminWhatsapp', '0812 3456 7890')
            ->where('publicWhatsappBubbleEnabled', false));

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/Login')
            ->where('publicAdminWhatsapp', '0812 3456 7890')
            ->where('publicWhatsappBubbleEnabled', false));
});

test('public contact cache is invalidated when its message template changes', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('publicWhatsappBubbleEnabled', true));

    MessageTemplate::query()->updateOrCreate(
        ['key' => PublicContactSettings::BUBBLE_ENABLED_KEY],
        ['body' => '0'],
    );

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('publicWhatsappBubbleEnabled', false));
});

test('non admin cannot change the public WhatsApp contact', function () {
    $athlete = User::factory()->create(['role' => 'athlete']);

    $this->actingAs($athlete)
        ->put(route('admin.whatsapp-template.update'), [
            'body' => PaymentReminderTemplate::DEFAULT_BODY,
            'contact_number' => '081111111111',
            'bubble_enabled' => false,
        ])
        ->assertForbidden();

    expect(MessageTemplate::query()->whereIn('key', [
        PublicContactSettings::CONTACT_KEY,
        PublicContactSettings::BUBBLE_ENABLED_KEY,
    ])->exists())->toBeFalse();
});
