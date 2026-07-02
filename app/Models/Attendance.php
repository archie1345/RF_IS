<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use SoftDeletes;

    protected $table = 'athlete_attendance';

    public $timestamps = true;

    protected $primaryKey = 'athlete_attendance_id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'athlete_id',
        'training_session_id',
        'date',
        'status',
        'checked_in_at',
        'notes',
        'follow_up_owner',
    ];

    protected $dates = ['deleted_at', 'date', 'checked_in_at'];

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class, 'athlete_id');
    }

    public function trainingSession(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id', 'training_session_id');
    }
}
