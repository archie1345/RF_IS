<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFile extends Model
{
    use HasUuids;

    public const DISK_PRIVATE = 'local';

    public const DISK_PUBLIC = 'public';

    protected $fillable = [
        'user_id',
        'file_type',
        'original_name',
        'file_path',
        'disk',
        'mime_type',
        'size_bytes',
    ];

    protected $hidden = [
        'file_path',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function storageDisk(): string
    {
        return $this->disk ?: self::DISK_PUBLIC;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
