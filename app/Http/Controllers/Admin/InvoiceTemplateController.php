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
            'logo_file' => [
                'sometimes',
                'nullable',
                File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(5 * 1024),
            ],
            'remove_logo_file' => ['sometimes', 'nullable', 'boolean'],
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
        $oldLogoPath = $template->logo_path;
        $oldQrisImagePath = $template->qris_image_path;
        $newLogoPath = $request->file('logo_file')?->store('invoice-logos', 'public');
        $newQrisImagePath = $request->file('qris_image')?->store('payment-qris', 'public');
        $removeLogo = (bool) ($validated['remove_logo_file'] ?? false);
        $removeQrisImage = (bool) ($validated['remove_qris_image'] ?? false);

        unset(
            $validated['logo_file'],
            $validated['remove_logo_file'],
            $validated['qris_image'],
            $validated['remove_qris_image'],
        );

        try {
            DB::transaction(function () use (
                $template,
                $validated,
                $newLogoPath,
                $removeLogo,
                $newQrisImagePath,
                $removeQrisImage,
            ): void {
                $template->fill($validated);

                if ($newLogoPath !== null) {
                    $template->logo_path = $newLogoPath;
                    $template->logo_url = null;
                } elseif ($removeLogo) {
                    $template->logo_path = null;
                    $template->logo_url = null;
                }

                if ($newQrisImagePath !== null) {
                    $template->qris_image_path = $newQrisImagePath;
                } elseif ($removeQrisImage) {
                    $template->qris_image_path = null;
                }

                $template->save();
            });
        } catch (\Throwable $exception) {
            if ($newLogoPath !== null) {
                Storage::disk('public')->delete($newLogoPath);
            }
            if ($newQrisImagePath !== null) {
                Storage::disk('public')->delete($newQrisImagePath);
            }

            throw $exception;
        }

        if ($oldLogoPath !== null && ($newLogoPath !== null || $removeLogo)) {
            Storage::disk('public')->delete($oldLogoPath);
        }
        if ($oldQrisImagePath !== null && ($newQrisImagePath !== null || $removeQrisImage)) {
            Storage::disk('public')->delete($oldQrisImagePath);
        }

        ActivityLogger::log(
            $request,
            'admin.invoice-template.updated',
            'admin',
            'Updated invoice or QRIS payment settings',
            $template,
            [
                'logo_replaced' => $newLogoPath !== null,
                'logo_removed' => $removeLogo && $newLogoPath === null,
                'qris_enabled' => $template->qris_enabled,
                'qris_image_replaced' => $newQrisImagePath !== null,
                'qris_image_removed' => $removeQrisImage && $newQrisImagePath === null,
            ],
        );

        return back();
    }
}
