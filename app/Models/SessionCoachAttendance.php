<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SessionCoachAttendance extends Model
{
    use SoftDeletes;

    protected $table = 'session_coach_attendance';
    protected $primaryKey = 'scaid';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        'coach_session_id',
        'coach_id',
        'status',
        'checked_at',
    ];

    protected $dates = ['checked_at', 'deleted_at'];

    public function coach()
    {
        return $this->belongsTo(Coach::class, 'coach_id', 'coach_id');
    }

    public function session()
    {
        return $this->belongsTo(Session::class, 'coach_session_id', 'csid');
    }
}

