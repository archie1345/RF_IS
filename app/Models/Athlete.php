<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Athlete extends Model{
    use SoftDeletes, HasFactory;
    protected $table = 'athletes';

    public $timestamps = false;

    protected $primaryKey = 'athlete_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'name',
        'bday',
        'gender',
        'height_cm',
        'weight_kg',
        'nik_hash',
        'bpjs_hash',
        'phone',
        'alamat',
        'geup',
        'id',
        'group_id',
        'parent_id',
        'branch_id',
    ];

    protected $hidden = [
        'nik_hash',
        'bpjs_hash',
    ];

    protected $dates = ['deleted_at'];

    public function group()
    {
        return $this->belongsTo(Group::class,'group_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class,'branch_id');
    }

    public function parent()
    {
        return $this->belongsTo(Parents::class,'parent_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class,'id');
    }
}