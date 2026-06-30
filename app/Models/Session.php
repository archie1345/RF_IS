<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Session extends Model
{
    use SoftDeletes;

    protected $table = 'coach_sessions';

    public $timestamps = true;

    protected $primaryKey = 'csid';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'coach_id',
        'branch_id',
        'group_id',
        'title',
        'location',
        'session_date',
        'start_time',
        'end_time',
        'status',
        'attendance_token_hash',
        'attendance_opens_at',
        'attendance_closes_at',
        'attendance_qr_generated_at',
        'attendance_qr_revoked_at',
    ];

    protected $dates = ['deleted_at', 'session_date', 'start_time', 'end_time'];

    protected $casts = [
        'attendance_opens_at' => 'datetime',
        'attendance_closes_at' => 'datetime',
        'attendance_qr_generated_at' => 'datetime',
        'attendance_qr_revoked_at' => 'datetime',
    ];

    public function coach(): BelongsTo
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

    public function coaches(): BelongsToMany
    {
        return $this->belongsToMany(Coach::class, 'coach_session_coaches', 'coach_session_id', 'coach_id')
            ->withTimestamps();
    }
}
