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
    public function __construct(private readonly PaymentReminderTemplate $template) {}

    public function edit(): Response
    {
        return Inertia::render('admin/WhatsAppTemplatePage', [
            'template' => [
                'body' => $this->template->body(),
            ],
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
        ], [
            'body.required' => 'Template WhatsApp wajib diisi.',
            'body.max' => 'Template WhatsApp maksimal 3000 karakter.',
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
        $this->template->clearCache();

        ActivityLogger::log(
            $request,
            'finance.whatsapp_template.updated',
            'finance',
            'Updated WhatsApp payment reminder template',
            $messageTemplate,
        );

        return back()->with('status', 'Template pengingat WhatsApp berhasil diperbarui.');
    }
}
