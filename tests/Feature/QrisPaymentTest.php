<?php

use App\Models\InvoiceTemplate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

it('shows an explicit QRIS placeholder when no official image is configured', function () {
    $athlete = User::factory()->create(['role' => 'athlete']);

    $this->actingAs($athlete)
        ->get(route('payments.qris'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('QrisPaymentPage')
            ->where('qris.enabled', true)
            ->where('qris.configured', false)
            ->where('qris.imageUrl', null)
        );
});

it('allows an admin to upload and edit the static QRIS configuration', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['role' => 'admin']);
    $image = UploadedFile::fake()->image('official-qris.png', 800, 800);

    $this->actingAs($admin)
        ->post(route('admin.invoice-template.update'), [
            'qris_enabled' => true,
            'qris_label' => 'QRIS RF Taekwondo',
            'qris_instructions' => 'Bayar sesuai sisa tagihan lalu unggah bukti.',
            'qris_image' => $image,
        ])
        ->assertRedirect();

    $template = InvoiceTemplate::query()->where('name', 'default')->firstOrFail();

    expect($template->qris_enabled)->toBeTrue()
        ->and($template->qris_label)->toBe('QRIS RF Taekwondo')
        ->and($template->qris_image_path)->not->toBeNull()
        ->and($template->qris_image_url)->not->toBeNull();

    Storage::disk('public')->assertExists($template->qris_image_path);
    expect($template->toArray())->not->toHaveKey('qris_image_path');
});

it('replaces the old QRIS image without leaving an orphaned file', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['role' => 'admin']);
    $oldPath = UploadedFile::fake()->image('old.png')->store('payment-qris', 'public');
    $template = InvoiceTemplate::query()->create([
        'name' => 'default',
        'company_name' => 'RF IS',
        'qris_enabled' => true,
        'qris_label' => 'QRIS Lama',
        'qris_image_path' => $oldPath,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.invoice-template.update'), [
            'qris_enabled' => true,
            'qris_label' => 'QRIS Baru',
            'qris_instructions' => 'Gunakan QR terbaru.',
            'qris_image' => UploadedFile::fake()->image('new.png'),
        ])
        ->assertRedirect();

    $template->refresh();

    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($template->qris_image_path);
});

it('allows an admin to remove the official QRIS image and return to the placeholder', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['role' => 'admin']);
    $path = UploadedFile::fake()->image('qris.png')->store('payment-qris', 'public');
    $template = InvoiceTemplate::query()->create([
        'name' => 'default',
        'company_name' => 'RF IS',
        'qris_enabled' => true,
        'qris_label' => 'Pembayaran QRIS',
        'qris_image_path' => $path,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.invoice-template.update'), [
            'qris_enabled' => true,
            'qris_label' => 'Pembayaran QRIS',
            'remove_qris_image' => true,
        ])
        ->assertRedirect();

    expect($template->refresh()->qris_image_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('forbids non-admin users from editing QRIS settings', function () {
    $athlete = User::factory()->create(['role' => 'athlete']);

    $this->actingAs($athlete)
        ->post(route('admin.invoice-template.update'), [
            'qris_enabled' => false,
            'qris_label' => 'Unauthorized change',
        ])
        ->assertForbidden();
});
