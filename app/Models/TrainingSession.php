<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingSession extends Model
{
    use SoftDeletes;

    protected $table = 'training_sessions';

    public $timestamps = true;

    protected $primaryKey = 'training_session_id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'weekly_training_schedule_id',
        'coach_id',
        'branch_id',
        'group_id',
        'session_type',
        'dedicated_athlete_id',
        'title',
        'location',
        'session_date',
        'start_time',
        'end_time',
        'status',
        'metadata',
        'attendance_token_hash',
        'attendance_qr_token',
        'attendance_opens_at',
        'attendance_closes_at',
        'attendance_qr_generated_at',
        'attendance_qr_revoked_at',
    ];

    protected $hidden = [
        'attendance_token_hash',
        'attendance_qr_token',
    ];

    protected $dates = ['deleted_at', 'session_date', 'start_time', 'end_time'];

    protected $casts = [
        'metadata' => 'array',
        'attendance_qr_token' => 'encrypted',
        'attendance_opens_at' => 'datetime',
        'attendance_closes_at' => 'datetime',
        'attendance_qr_generated_at' => 'datetime',
        'attendance_qr_revoked_at' => 'datetime',
    ];

    public function weeklyTrainingSchedule(): BelongsTo
    {
        return $this->belongsTo(WeeklyTrainingSchedule::class, 'weekly_training_schedule_id', 'weekly_training_schedule_id');
    }

    public function primaryCoach(): BelongsTo
    {
        return $this->belongsTo(Coach::class, 'coach_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id', 'group_id');
    }

    public function dedicatedAthlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class, 'dedicated_athlete_id', 'athlete_id');
    }

    public function assignedCoaches(): BelongsToMany
    {
        return $this->belongsToMany(Coach::class, 'training_session_coaches', 'training_session_id', 'coach_id')
            ->withTimestamps();
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'training_session_id', 'training_session_id');
    }
}
