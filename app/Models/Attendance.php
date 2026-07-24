<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use SoftDeletes;

    protected $table = 'athlete_attendance';

    protected $primaryKey = 'athlete_attendance_id';

    protected $fillable = [
        'athlete_id',
        'training_session_id',
        'date',
        'status',
        'checked_in_at',
        'notes',
        'follow_up_owner',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'checked_in_at' => 'datetime',
        ];
    }

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class, 'athlete_id', 'athlete_id');
    }

    public function trainingSession(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id', 'training_session_id');
    }
}
