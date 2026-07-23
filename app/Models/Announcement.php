<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    protected $fillable = [
        'created_by',
        'title',
        'message',
        'target_role',
        'is_active',
        'publish_at',
        'expire_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'publish_at' => 'datetime',
            'expire_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
