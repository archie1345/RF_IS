<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
        'group_name',
        'description'
    ];
    protected $dates = ['deleted_at'];
    public function athletes()
    {
        return $this->hasMany(Athlete::class,'group_id');
    }
}
