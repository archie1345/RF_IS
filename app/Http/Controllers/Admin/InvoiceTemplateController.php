<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvoiceTemplate;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

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
            'company_name' => ['required', 'string', 'max:150'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:60'],
            'company_email' => ['nullable', 'email', 'max:120'],
            'logo_url' => ['nullable', 'url', 'max:255'],
            'header_text' => ['nullable', 'string', 'max:255'],
            'footer_text' => ['nullable', 'string'],
            'payment_notes' => ['nullable', 'string'],
        ]);

        InvoiceTemplate::query()->updateOrCreate(
            ['name' => 'default'],
            $validated,
        );

        ActivityLogger::log($request, 'admin.invoice-template.updated', 'admin', 'Updated invoice template settings');

        return back();
    }
}
