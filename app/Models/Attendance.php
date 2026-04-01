<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use SoftDeletes;

    protected $table = 'athlete_attendance';

    public $timestamps = true;

    protected $primaryKey = 'atid';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'athlete_id',
        'date',
        'status',
    ];

    protected $dates = ['deleted_at','date'];

    public function athlete()
    {
        return $this->belongsTo(Athlete::class,'athlete_id');
    }

}
