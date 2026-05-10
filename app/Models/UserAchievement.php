<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserAchievement extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'event_id',
        'event_registration_id',
        'user_file_id',
        'championship_name',
        'medal',
        'location',
        'event_date',
        'class_name',
        'division',
        'category',
        'is_auto_recorded',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'is_auto_recorded' => 'boolean',
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
