<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use SoftDeletes;
    protected $table = 'branches';
    public $timestamps = true;
    
    protected $primaryKey = 'branch_id';

    public $incrementing = true;
    protected $keyType = 'int';
    protected $fillable = [
        'branch_name',
        'location',
    ];

    protected $dates = ['deleted_at'];

    public function athletes()
    {
        return $this->hasMany(Athlete::class,'branch_id');
    }
}
