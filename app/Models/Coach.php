<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coach extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'coaches';

    public $timestamps = true;

    protected $primaryKey = 'coach_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'status',
        'specialization',
        'bio',
        'nik_hash',
        'nik_ciphertext',
    ];

    protected $hidden = [
        'nik_hash',
        'nik_ciphertext',
    ];

    protected $dates = ['deleted_at'];

    protected function casts(): array
    {
        return [
            'nik_ciphertext' => 'encrypted',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'coach_id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id', 'id');
    }

    public function displayValue(string $column): string
    {
        return $this->sensitiveIdentifier($column, $column.'_hash');
    }

    private function sensitiveIdentifier(string $ciphertextColumn, string $hashColumn): string
    {
        if (blank($this->getRawOriginal($ciphertextColumn))) {
            return filled($this->getRawOriginal($hashColumn)) ? 'Stored as hash only' : 'Not stored';
        }

        try {
            return (string) $this->getAttribute($ciphertextColumn);
        } catch (DecryptException) {
            return 'Stored, cannot decrypt';
        }
    }
}