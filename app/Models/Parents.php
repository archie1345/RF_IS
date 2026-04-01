<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class parents extends Model
{
    protected $table = 'parents';
    public $timestamps = false;
    protected $primaryKey = 'parent_id';
    public $incrementing = true;
    protected $keyType = 'int';
    protected $fillable = [
        'id',
        'p_name',
        'p_phone',
    ];

    protected $dates = ['deleted_at'];

    public function athletes()
    {
        return $this->hasMany(Athlete::class,'parent_id');
    }
}
