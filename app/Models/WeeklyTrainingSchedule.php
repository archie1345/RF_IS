<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WeeklyTrainingSchedule extends Model
{
    use SoftDeletes;

    protected $table = 'weekly_training_schedules';

    protected $primaryKey = 'weekly_training_schedule_id';

    protected $fillable = [
        'title',
        'branch_id',
        'group_id',
        'dedicated_athlete_id',
        'coach_id',
        'session_type',
        'day_of_week',
        'start_time',
        'end_time',
        'location',
        'is_active',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_active' => 'boolean',
    ];

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

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class, 'coach_id', 'coach_id');
    }

    public function trainingSessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class, 'weekly_training_schedule_id', 'weekly_training_schedule_id');
    }
}
