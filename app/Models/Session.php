<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
    ];

    protected $dates = ['deleted_at','session_date','start_time','end_time'];

    public function coach()
    {
        return $this->belongsTo(Coach::class,'coach_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class,'branch_id');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id', 'group_id');
    }

    public function coaches(): BelongsToMany
    {
        return $this->belongsToMany(Coach::class, 'coach_session_coaches', 'coach_session_id', 'coach_id')
            ->withTimestamps();
    }
}
