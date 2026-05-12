<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserCertification extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'user_file_id',
        'cert_type',
        'title',
        'issuer',
        'certified_at',
        'expires_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'certified_at' => 'date',
            'expires_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(UserFile::class, 'user_file_id');
    }
}
