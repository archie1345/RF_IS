<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
        'location',
        'session_date',
        'start_time',
        'end_time',
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
}
