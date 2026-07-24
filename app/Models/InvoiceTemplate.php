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
        'header_text',
        'footer_text',
        'payment_notes',
        'qris_enabled',
        'qris_label',
        'qris_instructions',
        'qris_image_path',
    ];

    protected $hidden = [
        'qris_image_path',
    ];

    protected $appends = [
        'qris_image_url',
    ];

    protected function casts(): array
    {
        return [
            'qris_enabled' => 'boolean',
        ];
    }

    public function getQrisImageUrlAttribute(): ?string
    {
        if (blank($this->qris_image_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->qris_image_path);
    }

    public function qrisImageAbsolutePath(): ?string
    {
        if (blank($this->qris_image_path) || ! Storage::disk('public')->exists($this->qris_image_path)) {
            return null;
        }

        return Storage::disk('public')->path($this->qris_image_path);
    }
}
