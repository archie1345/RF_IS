<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MessageTemplate;
use App\Services\PaymentReminderTemplate;
use App\Services\PublicContactSettings;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppTemplateController extends Controller
{
    public const PUBLIC_CONTACT_KEY = PublicContactSettings::CONTACT_KEY;

    public function __construct(
        private readonly PaymentReminderTemplate $template,
        private readonly PublicContactSettings $publicContact,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('admin/WhatsAppTemplatePage', [
            'template' => [
                'body' => $this->template->body(),
            ],
            'contactNumber' => $this->publicContact->contactNumber(),
            'bubbleEnabled' => $this->publicContact->bubbleEnabled(),
            'defaultTemplate' => PaymentReminderTemplate::DEFAULT_BODY,
            'placeholders' => collect(PaymentReminderTemplate::PLACEHOLDERS)
                ->map(fn (string $description, string $key): array => [
                    'key' => $key,
                    'token' => '{'.$key.'}',
                    'description' => $description,
                ])
                ->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:3000'],
            // Optional for backwards-compatible API clients that only update
            // the reminder body. The admin UI always submits these fields.
            'contact_number' => ['sometimes', 'nullable', 'string', 'max:24', 'regex:/^[0-9+()\-\s]+$/'],
            'bubble_enabled' => ['sometimes', 'boolean'],
        ], [
            'body.required' => 'Template WhatsApp wajib diisi.',
            'body.max' => 'Template WhatsApp maksimal 3000 karakter.',
            'contact_number.regex' => 'Nomor WhatsApp admin tidak valid.',
        ]);

        $unsupported = $this->template->unsupportedPlaceholders($validated['body']);
        if ($unsupported !== []) {
            throw ValidationException::withMessages([
                'body' => 'Placeholder tidak dikenal: '.collect($unsupported)->map(fn (string $value): string => '{'.$value.'}')->implode(', ').'.',
            ]);
        }

        $messageTemplate = MessageTemplate::query()->updateOrCreate(
            ['key' => PaymentReminderTemplate::KEY],
            ['body' => trim($validated['body'])],
        );

        if (array_key_exists('contact_number', $validated)) {
            MessageTemplate::query()->updateOrCreate(
                ['key' => PublicContactSettings::CONTACT_KEY],
                ['body' => trim((string) ($validated['contact_number'] ?? ''))],
            );
        }

        if (array_key_exists('bubble_enabled', $validated)) {
            MessageTemplate::query()->updateOrCreate(
                ['key' => PublicContactSettings::BUBBLE_ENABLED_KEY],
                ['body' => $validated['bubble_enabled'] ? '1' : '0'],
            );
        }

        $this->template->clearCache();
        $this->publicContact->clearCache();

        ActivityLogger::log(
            $request,
            'finance.whatsapp_template.updated',
            'finance',
            'Updated WhatsApp payment reminder and public contact settings',
            $messageTemplate,
            [
                'public_contact_configured' => filled($this->publicContact->contactNumber()),
                'landing_bubble_enabled' => $this->publicContact->bubbleEnabled(),
            ],
        );

        return back()->with('status', 'Pengaturan WhatsApp berhasil diperbarui.');
    }
}
