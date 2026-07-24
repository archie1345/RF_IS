<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvoiceTemplate;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;

class InvoiceTemplateController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if (! Schema::hasTable('invoice_templates')) {
            return redirect()->route('admin.index')
                ->withErrors(['invoice_template' => 'invoice_templates table does not exist yet. Run migrations first.']);
        }

        $validated = $request->validate([
            'company_name' => ['sometimes', 'required', 'string', 'max:150'],
            'company_address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_phone' => ['sometimes', 'nullable', 'string', 'max:60'],
            'company_email' => ['sometimes', 'nullable', 'email', 'max:120'],
            'logo_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'header_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'footer_text' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'payment_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'qris_enabled' => ['sometimes', 'required', 'boolean'],
            'qris_label' => ['sometimes', 'required', 'string', 'max:150'],
            'qris_instructions' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'qris_image' => [
                'sometimes',
                'nullable',
                File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(5 * 1024),
            ],
            'remove_qris_image' => ['sometimes', 'nullable', 'boolean'],
        ]);

        $template = InvoiceTemplate::query()->firstOrNew(['name' => 'default']);
        $oldImagePath = $template->qris_image_path;
        $newImagePath = $request->file('qris_image')?->store('payment-qris', 'public');
        $removeImage = (bool) ($validated['remove_qris_image'] ?? false);

        unset($validated['qris_image'], $validated['remove_qris_image']);

        try {
            DB::transaction(function () use ($template, $validated, $newImagePath, $removeImage): void {
                $template->fill($validated);

                if ($newImagePath !== null) {
                    $template->qris_image_path = $newImagePath;
                } elseif ($removeImage) {
                    $template->qris_image_path = null;
                }

                $template->save();
            });
        } catch (\Throwable $exception) {
            if ($newImagePath !== null) {
                Storage::disk('public')->delete($newImagePath);
            }

            throw $exception;
        }

        if ($oldImagePath !== null && ($newImagePath !== null || $removeImage)) {
            Storage::disk('public')->delete($oldImagePath);
        }

        ActivityLogger::log(
            $request,
            'admin.invoice-template.updated',
            'admin',
            'Updated invoice or QRIS payment settings',
            $template,
            [
                'qris_enabled' => $template->qris_enabled,
                'qris_image_replaced' => $newImagePath !== null,
                'qris_image_removed' => $removeImage && $newImagePath === null,
            ],
        );

        return back();
    }
}
