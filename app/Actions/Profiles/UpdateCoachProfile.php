<?php

namespace App\Actions\Profiles;

use App\Models\Coach;
use App\Models\User;

class UpdateCoachProfile
{
    public function handle(User $user, array $data): void
    {
        $payload = [
            'status' => $data['status'],
            'specialization' => $data['specialization'] ?? null,
            'bio' => $data['bio'] ?? null,
        ];

        if (array_key_exists('nik', $data)) {
            $nik = $this->nullableString($data['nik'] ?? null);
            $payload['nik_hash'] = $nik ? hash('sha256', preg_replace('/\s+/', '', $nik)) : null;
            $payload['nik_ciphertext'] = $nik;
        }

        Coach::query()->updateOrCreate(
            ['id' => $user->id],
            $payload,
        );
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}