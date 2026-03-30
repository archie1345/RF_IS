<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Athlete extends Model{
    use SoftDeletes, HasFactory;
    protected $table = 'athletes';

    public $timestamps = false;

    protected $primaryKey = 'aid';
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
        'gid',
        'pid',
        'brid',
    ];

    protected $hidden = [
        'nik_hash',
        'bpjs_hash',
    ];

    protected $dates = ['deleted_at'];

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