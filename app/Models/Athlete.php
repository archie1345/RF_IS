<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Athlete extends Model{
    protected $primaryKey = 'aid';

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
        'uid',
        'gid',
        'pid',
        'brid'
    ];

    public function group()
    {
        return $this->belongsTo(Group::class,'gid');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class,'brid');
    }

    public function parent()
    {
        return $this->belongsTo(ParentModel::class,'pid');
    }
}