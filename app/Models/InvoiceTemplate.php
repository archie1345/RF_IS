<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class InvoiceTemplate extends Model
{
    protected $fillable = [
        'name',
        'company_name',
        'company_address',
        'company_phone',
        'company_email',
        'logo_url',
        'logo_path',
        'header_text',
        'footer_text',
        'payment_notes',
        'qris_enabled',
        'qris_label',
        'qris_instructions',
        'qris_image_path',
    ];

    protected $hidden = [
        'logo_path',
        'qris_image_path',
    ];

    protected $appends = [
        'logo_image_url',
        'qris_image_url',
    ];

    protected function casts(): array
    {
        return [
            'qris_enabled' => 'boolean',
        ];
    }

    public function getLogoImageUrlAttribute(): ?string
    {
        if ($this->hasLogoImage()) {
            return Storage::disk('public')->url($this->logo_path);
        }

        return filled($this->attributes['logo_url'] ?? null)
            ? (string) $this->attributes['logo_url']
            : null;
    }

    public function logoImageAbsolutePath(): ?string
    {
        if (! $this->hasLogoImage()) {
            return null;
        }

        return Storage::disk('public')->path($this->logo_path);
    }

    public function logoImageDataUri(): ?string
    {
        if (! $this->hasLogoImage()) {
            return null;
        }

        $disk = Storage::disk('public');
        $mimeType = $disk->mimeType($this->logo_path) ?: 'image/png';

        return 'data:'.$mimeType.';base64,'.base64_encode($disk->get($this->logo_path));
    }

    public function getQrisImageUrlAttribute(): ?string
    {
        if (! $this->hasQrisImage()) {
            return null;
        }

        return Storage::disk('public')->url($this->qris_image_path);
    }

    public function qrisImageAbsolutePath(): ?string
    {
        if (! $this->hasQrisImage()) {
            return null;
        }

        return Storage::disk('public')->path($this->qris_image_path);
    }

    public function qrisImageDataUri(): ?string
    {
        if (! $this->hasQrisImage()) {
            return null;
        }

        $disk = Storage::disk('public');
        $mimeType = $disk->mimeType($this->qris_image_path) ?: 'image/png';

        return 'data:'.$mimeType.';base64,'.base64_encode($disk->get($this->qris_image_path));
    }

    private function hasLogoImage(): bool
    {
        return filled($this->logo_path)
            && Storage::disk('public')->exists($this->logo_path);
    }

    private function hasQrisImage(): bool
    {
        return filled($this->qris_image_path)
            && Storage::disk('public')->exists($this->qris_image_path);
    }
}
