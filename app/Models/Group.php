<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    use SoftDeletes;

    protected $table = 'class_groups';

    protected $primaryKey = 'group_id';

    protected $fillable = [
        'branch_id',
        'training_group_id',
        'coach_id',
        'dedicated_athlete_id',
        'group_name',
        'class_type',
        'schedule_mode',
        'single_session_date',
        'day_of_week',
        'start_time',
        'end_time',
        'min_belt',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'single_session_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function scopeAssignedToCoach(Builder $query, mixed $coachId): Builder
    {
        if (! $coachId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $assigned) use ($coachId): void {
            $assigned->where('coach_id', $coachId)
                ->orWhereHas('coaches', fn (Builder $coaches) => $coaches->where('coaches.coach_id', $coachId));
        });
    }

    public function athletes(): HasMany
    {
        return $this->hasMany(Athlete::class, 'group_id', 'group_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    public function trainingGroup(): BelongsTo
    {
        return $this->belongsTo(TrainingGroup::class, 'training_group_id', 'id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class, 'coach_id', 'coach_id');
    }

    public function coaches(): BelongsToMany
    {
        return $this->belongsToMany(Coach::class, 'class_group_coaches', 'group_id', 'coach_id', 'group_id', 'coach_id')
            ->withTimestamps();
    }

    public function dedicatedAthlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class, 'dedicated_athlete_id', 'athlete_id');
    }

    public function privateAthletes(): BelongsToMany
    {
        return $this->belongsToMany(Athlete::class, 'class_group_private_athletes', 'group_id', 'athlete_id', 'group_id', 'athlete_id')
            ->withTimestamps();
    }

    public function weeklySchedules(): HasMany
    {
        return $this->hasMany(WeeklyTrainingSchedule::class, 'group_id', 'group_id');
    }

    public function trainingSessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class, 'group_id', 'group_id');
    }
}
