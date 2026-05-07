<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Parents extends Model
{
    use SoftDeletes;

    protected $table = 'parents';
    public $timestamps = true;
    protected $primaryKey = 'parent_id';
    public $incrementing = true;
    protected $keyType = 'int';
    protected $fillable = [
        'id',
        'relation',
        'occupation',
        'notes',
    ];

    protected $dates = ['deleted_at'];

    public function athletes()
    {
        return $this->hasMany(Athlete::class,'parent_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id', 'id');
    }
}
