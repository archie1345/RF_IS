<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    use SoftDeletes;

    protected $table = 'class_groups';

    public $timestamps = true;

    protected $primaryKey = 'group_id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'branch_id',
        'coach_id',
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

    protected $dates = ['deleted_at', 'single_session_date'];

    protected $casts = [
        'day_of_week' => 'integer',
        'single_session_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function athletes(): HasMany
    {
        return $this->hasMany(Athlete::class, 'group_id', 'group_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class, 'coach_id', 'coach_id');
    }
}
