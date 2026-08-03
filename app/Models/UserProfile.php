<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id',
        'profile_picture_path',
        'bio',
        'address',
        'nik_hash',
        'nik_ciphertext',
        'bpjs_hash',
        'bpjs_ciphertext',
    ];

    protected $hidden = [
        'nik_hash',
        'nik_ciphertext',
        'bpjs_hash',
        'bpjs_ciphertext',
    ];

    protected function casts(): array
    {
        return [
            'nik_ciphertext' => 'encrypted',
            'bpjs_ciphertext' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function displayNik(): string
    {
        return $this->decryptValue('nik_ciphertext', 'nik_hash');
    }

    public function displayBpjs(): string
    {
        return $this->decryptValue('bpjs_ciphertext', 'bpjs_hash');
    }

    private function decryptValue(string $cipherCol, string $hashCol): string
    {
        if (blank($this->getRawOriginal($cipherCol))) {
            return filled($this->getRawOriginal($hashCol)) ? 'Stored as hash only' : '';
        }

        try {
            return (string) $this->getAttribute($cipherCol);
        } catch (DecryptException) {
            return 'Stored, cannot decrypt';
        }
    }
}
