<?php

use App\Models\InvoiceTemplate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

it('allows an admin to upload replace and remove an invoice logo file', function () {
    Storage::fake('public');

    $admin = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.invoice-template.update'), [
            'company_name' => 'Rhino Fighter Indonesia',
            'company_address' => 'Jl. Demo No. 10, Malang',
            'company_phone' => '081234567890',
            'company_email' => 'finance@rfis.test',
            'header_text' => 'Official payment document',
            'footer_text' => 'Thank you for training with Rhino Fighter.',
            'payment_notes' => 'Use the invoice number as the payment reference.',
            'logo_file' => UploadedFile::fake()->image('rfis-logo.png', 600, 240),
        ])
        ->assertRedirect();

    $template = InvoiceTemplate::query()->where('name', 'default')->firstOrFail();
    $firstPath = $template->logo_path;

    expect($firstPath)->not->toBeNull()
        ->and($template->logo_image_url)->toContain('/storage/invoice-logos/')
        ->and($template->logoImageDataUri())->toStartWith('data:image/png;base64,');
    Storage::disk('public')->assertExists($firstPath);

    $this->get(route('payments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('PaymentsPage')
            ->where('invoiceTemplate.company_name', 'Rhino Fighter Indonesia')
            ->where('invoiceTemplate.logo_image_url', $template->logo_image_url));

    $this->post(route('admin.invoice-template.update'), [
        'company_name' => 'Rhino Fighter Indonesia',
        'logo_file' => UploadedFile::fake()->image('rfis-logo-new.jpg', 500, 200),
    ])->assertRedirect();

    $template->refresh();
    $secondPath = $template->logo_path;

    expect($secondPath)->not->toBe($firstPath);
    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($secondPath);

    $this->post(route('admin.invoice-template.update'), [
        'company_name' => 'Rhino Fighter Indonesia',
        'remove_logo_file' => true,
    ])->assertRedirect();

    $template->refresh();

    expect($template->logo_path)->toBeNull()
        ->and($template->logo_image_url)->toBeNull();
    Storage::disk('public')->assertMissing($secondPath);
});

it('renders the professional invoice layout with an embedded uploaded logo', function () {
    Storage::fake('public');

    $path = UploadedFile::fake()->image('invoice-logo.png', 600, 240)->store('invoice-logos', 'public');
    $template = InvoiceTemplate::query()->create([
        'name' => 'default',
        'company_name' => 'Rhino Fighter Indonesia',
        'company_address' => 'Jl. Demo No. 10, Malang',
        'company_phone' => '081234567890',
        'company_email' => 'finance@rfis.test',
        'logo_path' => $path,
        'header_text' => 'Official payment document',
        'footer_text' => 'Thank you for training with Rhino Fighter.',
        'payment_notes' => 'Use the invoice number as the payment reference.',
    ]);

    $html = view('pdf.invoice', [
        'template' => $template,
        'invoice' => [
            'is_payroll' => false,
            'document_title' => 'INVOICE',
            'recipient_label' => 'Ditagihkan kepada',
            'invoice_number' => 'INV-TEST-001',
            'invoice_date' => '27 Jul 2026',
            'due_date' => '10 Aug 2026',
            'collection_method' => 'TRANSFER',
            'athlete_name' => 'Adit Pratama',
            'athlete_email' => 'athlete@rfis.test',
            'payment_type' => 'Iuran / SPP',
            'status' => 'PENDING',
            'total_amount' => 300000,
            'paid_amount' => 100000,
            'remaining_amount' => 200000,
            'notes' => 'Iuran bulan Juli 2026',
            'payroll_period' => null,
            'payroll_basis' => null,
            'payroll_units' => null,
            'payroll_rate' => 0,
            'payroll_base_amount' => 0,
            'payroll_bonus_amount' => 0,
        ],
    ])->render();

    expect($html)
        ->toContain('data:image/png;base64,')
        ->toContain('Rincian tagihan')
        ->toContain('BELUM LUNAS')
        ->toContain('Dokumen diterbitkan secara elektronik')
        ->toContain('INV-TEST-001');
});
