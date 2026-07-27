<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MessageTemplate;
use App\Services\PaymentReminderTemplate;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppTemplateController extends Controller
{
    public const PUBLIC_CONTACT_KEY = 'public_admin_whatsapp';

    public function __construct(private readonly PaymentReminderTemplate $template) {}

    public function edit(): Response
    {
        return Inertia::render('admin/WhatsAppTemplatePage', [
            'template' => [
                'body' => $this->template->body(),
            ],
            'contactNumber' => MessageTemplate::query()
                ->where('key', self::PUBLIC_CONTACT_KEY)
                ->value('body') ?? '',
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
            // the reminder body. The admin UI always submits this field.
            'contact_number' => ['nullable', 'string', 'max:24', 'regex:/^[0-9+()\-\s]+$/'],
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

        if (array_key_exists('contact_number', $validated) && filled($validated['contact_number'])) {
            MessageTemplate::query()->updateOrCreate(
                ['key' => self::PUBLIC_CONTACT_KEY],
                ['body' => trim($validated['contact_number'])],
            );
        }

        $this->template->clearCache();

        ActivityLogger::log(
            $request,
            'finance.whatsapp_template.updated',
            'finance',
            'Updated WhatsApp payment reminder template and public admin contact',
            $messageTemplate,
        );

        return back()->with('status', 'Pengaturan WhatsApp berhasil diperbarui.');
    }
}
