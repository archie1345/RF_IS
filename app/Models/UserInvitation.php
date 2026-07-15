<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserInvitation extends Model
{
    protected $fillable = [
        'user_id',
        'token_hash',
        'expires_at',
        'accepted_at',
        'invalidated_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return now()->greaterThanOrEqualTo($this->expires_at);
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isInvalidated(): bool
    {
        return $this->invalidated_at !== null;
    }

    public function isAcceptable(): bool
    {
        return ! $this->isExpired() && ! $this->isAccepted() && ! $this->isInvalidated();
    }
}
