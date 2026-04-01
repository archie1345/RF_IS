<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Coach extends Model
{
    use SoftDeletes;

    protected $table = 'coaches';
    public $timestamps = true;
    protected $primaryKey = 'coach_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'status',
        'specialization',
        'bio',
    ];

    protected $dates = ['deleted_at'];

    public function user()
    {
        return $this->belongsTo(User::class, 'id', 'id');
    }
}
