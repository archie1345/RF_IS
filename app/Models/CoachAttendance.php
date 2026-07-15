<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CoachAttendance extends Model
{
    use SoftDeletes;

    protected $table = 'coach_attendance';

    protected $primaryKey = 'coach_attendance_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'training_session_id',
        'coach_id',
        'status',
        'checked_at',
    ];

    protected $dates = ['checked_at', 'deleted_at'];

    public function coach()
    {
        return $this->belongsTo(Coach::class, 'coach_id', 'coach_id');
    }

    public function trainingSession()
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id', 'training_session_id');
    }
}
