<?php

namespace App\Support\Profile;

use Illuminate\Validation\Rule;

class ProfileFormRules
{
    public function accountProfile(): array
    {
        return [
            'bio' => ['nullable', 'string'],
            'profile_picture' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    public function certification(): array
    {
        return [
            'cert_type' => ['required', Rule::in(['BELT', 'REFEREE', 'TRAINER'])],
            'title' => ['required', 'string', 'max:120'],
            'issuer' => ['nullable', 'string', 'max:120'],
            'certified_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'file' => $this->documentFile(),
        ];
    }

    public function achievement(): array
    {
        return [
            'championship_name' => ['required', 'string', 'max:120'],
            'medal' => ['required', Rule::in(['GOLD', 'SILVER', 'BRONZE', 'NONE'])],
            'location' => ['nullable', 'string', 'max:160'],
            'event_date' => ['nullable', 'date'],
            'class_name' => ['nullable', 'string', 'max:120'],
            'division' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
            'file' => $this->documentFile(),
        ];
    }

    public function documentFile(): array
    {
        return ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'];
    }
}
